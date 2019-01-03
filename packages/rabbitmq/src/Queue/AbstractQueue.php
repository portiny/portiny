<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Queue;

use Bunny\Channel;
use Bunny\Exception\BunnyException;
use Bunny\Protocol\MethodQueueBindOkFrame;
use Bunny\Protocol\MethodQueueDeclareOkFrame;

abstract class AbstractQueue
{
	final public function declare(Channel $channel): void
	{
		$frame = $channel->queueDeclare(
			$this->getName(),
			$this->isPassive(),
			$this->isDurable(),
			$this->isExclusive(),
			$this->isAutoDelete(),
			FALSE,
			$this->getArguments()
		);
		if (! $frame instanceof MethodQueueDeclareOkFrame) {
			throw new BunnyException(sprintf('Could not declare queue "%s".', $this->getName()));
		}

		foreach ($this->getBindings() as $queueBind) {
			$frame = $channel->queueBind(
				$this->getName(),
				$queueBind->getExchange(),
				$queueBind->getRoutingKey(),
				FALSE,
				$queueBind->getArguments()
			);
			if (! $frame instanceof MethodQueueBindOkFrame) {
				throw new BunnyException(
					sprintf(
						'Could not bind queue "%s" to "%s" with routing key "%s".',
						$this->getName(),
						$queueBind->getExchange(),
						$queueBind->getRoutingKey()
					)
				);
			}
		}
	}

	abstract protected function getName(): string;

	protected function isPassive(): bool
	{
		return false;
	}

	protected function isDurable(): bool
	{
		return false;
	}

	protected function isExclusive(): bool
	{
		return false;
	}

	protected function isAutoDelete(): bool
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

	/**
	 * @return QueueBind[]
	 */
	protected function getBindings(): array
	{
		return [];
	}
}
