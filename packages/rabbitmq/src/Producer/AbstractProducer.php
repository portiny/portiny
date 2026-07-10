<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Producer;

use Bunny\Channel;
use Portiny\RabbitMQ\BunnyManager;
use React\Promise\PromiseInterface;

abstract class AbstractProducer
{
	public const DELIVERY_MODE_NON_PERSISTENT = 1;

	public const DELIVERY_MODE_PERSISTENT = 2;

	public const CONTENT_TYPE_APPLICATION_JSON = 'application/json';

	/**
	 * Name prefix of the per-delay holding queues declared by produceWithDelay().
	 *
	 * The holding queues went through three generations; RabbitMQ rejects a queue.declare
	 * whose arguments differ from the existing queue's (PRECONDITION_FAILED) and x-queue-type
	 * can never change at all, so incompatible generations must not share a live queue name:
	 *
	 * - "delay"   (<= 8.4) — CLASSIC transient holding queues (single-node; unavailable during
	 *   a broker-node reboot). All of them delete themselves via their own x-expires within
	 *   delayMs + 10s of the last such producer stopping.
	 * - "delay-q" (8.5) — QUORUM holding queues, still with x-expires. Broken: on quorum
	 *   queues neither queue.declare nor basic.publish refreshes the x-expires lease (unlike
	 *   classic queues, where a redeclare resets it), so the queue is deleted a fixed
	 *   delayMs + 10s after creation and silently drops any delayed message published into it
	 *   more than 10s after the queue came into existence. Existing ones self-expire.
	 * - "delay"   (current) — QUORUM holding queues WITHOUT x-expires: permanent,
	 *   Raft-replicated, one per (exchange, routingKey, delayMs) combination. The historical
	 *   name is reused; that is safe once every classic "delay_*" queue has expired, i.e.
	 *   delayMs + 10s after the last <= 8.4 producer stopped. Upgrading straight from <= 8.4
	 *   while classic queues still exist makes the declare fail with PRECONDITION_FAILED
	 *   until they expire — and rolling back to <= 8.5 while the quorum queues exist fails
	 *   the same way (delete the "delay_*" queues first).
	 */
	private const DELAY_QUEUE_NAME_PREFIX = 'delay';


	/**
	 * @param mixed $body
	 * @return bool|int|PromiseInterface
	 */
	final public function produce(Channel $channel, $body)
	{
		return $channel->publish(
			$body,
			$this->getHeaders(),
			$this->getExchangeName(),
			$this->getRoutingKey(),
			$this->isMandatory(),
			$this->isImmediate()
		);
	}


	/**
	 * Publish a message with a delay via native RabbitMQ DLX + per-delay TTL queue.
	 * Replicates Symfony Messenger Connection::publishWithDelay semantics.
	 *
	 * When $delayMs <= 0 the message is published immediately via the standard produce() path.
	 * Otherwise a durable QUORUM holding queue named delay_{exchange}_{routingKey}_{delayMs}
	 * is declared idempotently with x-message-ttl, x-dead-letter-exchange and
	 * x-dead-letter-routing-key, and the message is published there.  After the TTL the broker
	 * dead-letters it into the real exchange with the original routing key.
	 *
	 * The holding queue is a quorum queue (Raft-replicated across the broker nodes) so that a
	 * single-node reboot neither loses the delayed messages nor makes this declare fail: a
	 * classic holding queue lives on exactly one node, and while that node is down the declare
	 * raises a NOT_FOUND channel exception ("process is stopped by supervisor"), failing every
	 * delayed publish for the duration of the reboot.
	 *
	 * The queue deliberately has NO x-expires: on quorum queues the expiry lease is never
	 * refreshed — not by queue.declare (which does refresh it on classic queues) and not by
	 * basic.publish — so an expiring quorum queue is deleted a fixed interval after creation
	 * and silently drops any not-yet-dead-lettered message it still holds (verified against
	 * RabbitMQ 4.3.1). The holding queues are therefore permanent; their count is bounded by
	 * the number of distinct (exchange, routingKey, delayMs) combinations in use.
	 *
	 * BunnyManager::declareExchanges() is responsible for declaring the static
	 * BunnyManager::DELAYS_EXCHANGE ('delays') exchange once at startup — this method
	 * only declares the per-delay holding queue (dynamic, lazy).
	 *
	 * @param mixed $body
	 * @return bool|int|PromiseInterface
	 */
	final public function produceWithDelay(Channel $channel, $body, int $delayMs)
	{
		if ($delayMs <= 0) {
			return $this->produce($channel, $body);
		}

		$exchange = $this->getExchangeName();
		$routingKey = $this->getRoutingKey();
		$delayQueue = sprintf('%s_%s_%s_%d', self::DELAY_QUEUE_NAME_PREFIX, $exchange, $routingKey, $delayMs);

		$channel->queueDeclare(
			$delayQueue,
			false,
			true,
			false,
			false,
			false,
			[
				'x-queue-type' => 'quorum',
				'x-message-ttl' => $delayMs,
				'x-dead-letter-exchange' => $exchange,
				'x-dead-letter-routing-key' => $routingKey,
			]
		);

		$channel->queueBind($delayQueue, BunnyManager::DELAYS_EXCHANGE, $delayQueue);

		return $channel->publish(
			$body,
			$this->getHeaders(),
			BunnyManager::DELAYS_EXCHANGE,
			$delayQueue,
			$this->isMandatory(),
			$this->isImmediate()
		);
	}


	/**
	 * Name of the RabbitMQ connection this producer belongs to.
	 *
	 * Must return a constant value independent of the object state, as integrations read it via reflection
	 * to assign the component to a connection.
	 */
	public function getConnectionName(): string
	{
		return 'default';
	}


	abstract protected function getExchangeName(): string;


	abstract protected function getRoutingKey(): string;


	protected function getHeaders(): array
	{
		return [];
	}


	protected function isMandatory(): bool
	{
		return false;
	}


	protected function isImmediate(): bool
	{
		return false;
	}

}
