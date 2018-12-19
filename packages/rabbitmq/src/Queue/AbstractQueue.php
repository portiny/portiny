<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Queue;

abstract class AbstractQueue
{
	abstract public function getName(): string;

	public function isPassive(): bool
	{
		return false;
	}

	public function isDurable(): bool
	{
		return false;
	}

	public function isExclusive(): bool
	{
		return false;
	}

	public function isAutoDelete(): bool
	{
		return false;
	}

	public function isNoWait(): bool
	{
		return false;
	}

	public function getArguments(): array
	{
		return [];
	}

	/**
	 * @return QueueBind[]
	 */
	public function getBindings(): array
	{
		return [];
	}
}
