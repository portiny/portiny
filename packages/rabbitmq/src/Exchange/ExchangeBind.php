<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Exchange;

final class ExchangeBind
{
	/**
	 * @var string
	 */
	private $destination;

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


	public function __construct(
		string $destination,
		string $routingKey = '',
		bool $nowait = false,
		array $arguments = []
	) {
		$this->destination = $destination;
		$this->routingKey = $routingKey;
		$this->nowait = $nowait;
		$this->arguments = $arguments;
	}


	public function getDestination(): string
	{
		return $this->destination;
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
