<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Queue;

final class QueueBind
{
	/**
	 * @var string
	 */
	private $exchange;

	/**
	 * @var string
	 */
	private $routingKey;

	/**
	 * @var bool
	 */
	private $nowait = false;

	/**
	 * @var array
	 */
	private $arguments = [];

	public function __construct(string $exchange, string $routingKey = '', bool $nowait = false, array $arguments = [])
	{
		$this->exchange = $exchange;
		$this->routingKey = $routingKey;
		$this->nowait = $nowait;
		$this->arguments = $arguments;
	}

	public function getExchange(): string
	{
		return $this->exchange;
	}

	public function getRoutingKey(): string
	{
		return $this->routingKey;
	}

	public function isNowait(): bool
	{
		return $this->nowait;
	}

	public function getArguments(): array
	{
		return $this->arguments;
	}
}
