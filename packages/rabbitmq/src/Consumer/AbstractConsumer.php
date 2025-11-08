<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Consumer;

use Bunny\Async\Client as AsyncClient;
use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use InvalidArgumentException;
use React\Promise\PromiseInterface;
use Throwable;

abstract class AbstractConsumer
{
	public const MESSAGE_ACK = 1;

	public const MESSAGE_NACK = 2;

	public const MESSAGE_REJECT = 3;

	public const MESSAGE_REJECT_REQUEUE = 4;

	/**
	 * @var int
	 */
	private $consumedMessages = 0;


	/**
	 * @return MethodBasicConsumeOkFrame|PromiseInterface
	 */
	final public function consume(Channel $channel, ?int $numberOfMessages = null)
	{
		$channel->qos($this->getPrefetchSize(), $this->getPrefetchCount());

		return $channel->consume(
			function (Message $message, Channel $channel, $client) use ($numberOfMessages): void {
				$this->beforeProcess($message);
				try {
					$result = $this->process($message);
					$this->afterProcess($message);
				} catch (Throwable $throwable) {
					$this->errorProcess($message, $throwable);
					$channel->reject($message);
					throw $throwable;
				}

				switch ($result) {
					case self::MESSAGE_ACK:
						$channel->ack($message);
						break;
					case self::MESSAGE_NACK:
						$channel->nack($message);
						break;
					case self::MESSAGE_REJECT:
						$channel->reject($message, false);
						break;
					case self::MESSAGE_REJECT_REQUEUE:
						$channel->reject($message);
						break;
					default:
						$channel->reject($message);
						throw new InvalidArgumentException('Unknown return value of consumer');
				}

				if ($numberOfMessages !== null && ++$this->consumedMessages >= $numberOfMessages) {
					if ($client instanceof Client || $client instanceof AsyncClient) {
						$client->stop();
					}
				}
			},
			$this->getQueueName(),
			$this->getConsumerTag(),
			$this->isNoLocal(),
			$this->isNoAck(),
			$this->isExclusive(),
			$this->isNoWait(),
			$this->getArguments()
		);
	}


	abstract protected function process(Message $message): int;


	abstract protected function getQueueName(): string;


	protected function getConsumerTag(): string
	{
		return '';
	}


	protected function isNoLocal(): bool
	{
		return false;
	}


	protected function isNoAck(): bool
	{
		return false;
	}


	protected function isExclusive(): bool
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


	protected function getPrefetchSize(): int
	{
		return 0;
	}


	protected function getPrefetchCount(): int
	{
		return 1;
	}

	protected function beforeProcess(Message $message): void
	{
	}


	protected function afterProcess(Message $message): void
	{
	}


	protected function errorProcess(Message $message, Throwable $throwable): void
	{
	}

}
