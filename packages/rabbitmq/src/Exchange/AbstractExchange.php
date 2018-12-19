<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Exchange;

abstract class AbstractExchange
{
	/**
	 * @var string
	 */
	public const TYPE_DIRECT = 'direct';

	/**
	 * @var string
	 */
	public const TYPE_HEADERS = 'headers';

	/**
	 * @var string
	 */
	public const TYPE_FANOUT = 'fanout';

	/**
	 * @var string
	 */
	public const TYPE_TOPIC = 'topic';

	/**
	 * @var array
	 */
	public const AVAILABLE_TYPES = [self::TYPE_DIRECT, self::TYPE_HEADERS, self::TYPE_FANOUT, self::TYPE_TOPIC];

	abstract public function getName(): string;

	/**
	 * @return ExchangeBind[]
	 */
	public function getBindings(): array
	{
		return [];
	}

	public function getType(): string
	{
		return self::TYPE_DIRECT;
	}

	public function isPassive(): bool
	{
		return false;
	}

	public function isDurable(): bool
	{
		return false;
	}

	public function isAutoDelete(): bool
	{
		return false;
	}

	public function isInternal(): bool
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
}
