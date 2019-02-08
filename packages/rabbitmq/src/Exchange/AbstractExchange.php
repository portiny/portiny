<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Exchange;

use Bunny\Channel;
use Bunny\Exception\BunnyException;
use Bunny\Protocol\MethodExchangeBindOkFrame;
use Bunny\Protocol\MethodExchangeDeclareOkFrame;

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

	/**
	 * @throws BunnyException
	 */
	final public function declare(Channel $channel): void
	{
		$frame = $channel->exchangeDeclare(
			$this->getName(),
			$this->getType(),
			$this->isPassive(),
			$this->isDurable(),
			$this->isAutoDelete(),
			$this->isInternal(),
			false,
			$this->getArguments()
		);
		if (! $frame instanceof MethodExchangeDeclareOkFrame) {
			throw new BunnyException(sprintf('Could not declare exchange "%s".', $this->getName()));
		}
	}

	/**
	 * @throws BunnyException
	 */
	final public function declareBindings(Channel $channel): void
	{
		foreach ($this->getBindings() as $exchangeBind) {
			$frame = $channel->exchangeBind(
				$exchangeBind->getDestination(),
				$this->getName(),
				$exchangeBind->getRoutingKey(),
				false,
				$exchangeBind->getArguments()
			);
			if (! $frame instanceof MethodExchangeBindOkFrame) {
				throw new BunnyException(
					sprintf(
						'Could not bind exchange "%s" to "%s" with routing key "%s".',
						$exchangeBind->getDestination(),
						$this->getName(),
						$exchangeBind->getRoutingKey()
					)
				);
			}
		}
	}

	abstract protected function getName(): string;

	/**
	 * @return ExchangeBind[]
	 */
	protected function getBindings(): array
	{
		return [];
	}

	protected function getType(): string
	{
		return self::TYPE_DIRECT;
	}

	protected function isPassive(): bool
	{
		return false;
	}

	protected function isDurable(): bool
	{
		return false;
	}

	protected function isAutoDelete(): bool
	{
		return false;
	}

	protected function isInternal(): bool
	{
		return false;
	}

	protected function isNoWait(): bool
	{
		return false;
	}

	protected function getArguments(): array
	{
		return [];
	}
}
