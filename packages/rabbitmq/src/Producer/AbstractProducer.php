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

	private const DELAY_QUEUE_EXPIRE_SLACK_MS = 10000;

	/**
	 * Name prefix of the per-delay holding queues declared by produceWithDelay().
	 *
	 * "delay-q" (quorum) deliberately differs from the historical "delay" prefix under which
	 * the holding queues were declared as CLASSIC queues: an existing queue can never change
	 * its x-queue-type, so redeclaring an old classic "delay_*" queue with 'quorum' would fail
	 * with PRECONDITION_FAILED. Under a new name, producers running older and newer versions
	 * of this package coexist safely, and the old classic queues drain their remaining delayed
	 * messages and then delete themselves via their own x-expires.
	 */
	private const DELAY_QUEUE_NAME_PREFIX = 'delay-q';


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
	 * Otherwise a durable QUORUM holding queue named delay-q_{exchange}_{routingKey}_{delayMs}
	 * is declared idempotently with x-message-ttl, x-expires, x-dead-letter-exchange and
	 * x-dead-letter-routing-key, and the message is published there.  After the TTL the broker
	 * dead-letters it into the real exchange with the original routing key.
	 *
	 * The holding queue is a quorum queue (Raft-replicated across the broker nodes) so that a
	 * single-node reboot neither loses the delayed messages nor makes this declare fail: a
	 * classic holding queue lives on exactly one node, and while that node is down the declare
	 * raises a NOT_FOUND channel exception ("process is stopped by supervisor"), failing every
	 * delayed publish for the duration of the reboot.
	 *
	 * BunnyManager::declareExchanges() is responsible for declaring the static
	 * BunnyManager::DELAYS_EXCHANGE ('delays') exchange once at startup — this method
	 * only declares the per-delay holding queue (dynamic, lazy, x-expires-scoped).
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
				'x-expires' => $delayMs + self::DELAY_QUEUE_EXPIRE_SLACK_MS,
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
