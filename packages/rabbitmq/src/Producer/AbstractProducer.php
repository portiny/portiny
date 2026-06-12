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
	 * Otherwise a durable holding queue named delay_{exchange}_{routingKey}_{delayMs} is
	 * declared idempotently with x-message-ttl, x-expires, x-dead-letter-exchange and
	 * x-dead-letter-routing-key, and the message is published there.  After the TTL the broker
	 * dead-letters it into the real exchange with the original routing key.
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
		$delayQueue = sprintf('delay_%s_%s_%d', $exchange, $routingKey, $delayMs);

		$channel->queueDeclare(
			$delayQueue,
			false,
			true,
			false,
			false,
			false,
			[
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
