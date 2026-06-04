<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Producer;

use Bunny\Channel;
use React\Promise\PromiseInterface;

abstract class AbstractProducer
{
	public const DELIVERY_MODE_NON_PERSISTENT = 1;

	public const DELIVERY_MODE_PERSISTENT = 2;

	public const CONTENT_TYPE_APPLICATION_JSON = 'application/json';


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
