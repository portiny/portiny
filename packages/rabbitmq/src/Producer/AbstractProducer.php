<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Producer;

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

	abstract public function getExchangeName(): string;

	abstract public function getRoutingKey(): string;

	// "routing key" = "queue name" (if exchange name is empty)

	public function getHeaders(): array
	{
		return [];
	}

	public function isMandatory(): bool
	{
		return false;
	}

	public function isImmediate(): bool
	{
		return false;
	}
}
