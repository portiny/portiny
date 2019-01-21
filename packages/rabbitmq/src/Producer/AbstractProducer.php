<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Producer;

use Bunny\Channel;
use React\Promise\PromiseInterface;

abstract class AbstractProducer
{
	/**
	 * @var int
	 */
	public const DELIVERY_MODE_NON_PERSISTENT = 1;

	/**
	 * @var int
	 */
	public const DELIVERY_MODE_PERSISTENT = 2;

	/**
	 * @var string
	 */
	public const CONTENT_TYPE_APPLICATION_JSON = 'application/json';

	/**
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
