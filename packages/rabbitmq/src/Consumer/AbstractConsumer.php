<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Consumer;

use Bunny\Async\Client as AsyncClient;
use Bunny\Channel;
use Bunny\Client;
use Bunny\Message;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use InvalidArgumentException;
use React\Promise\PromiseInterface;

abstract class AbstractConsumer
{
	/**
	 * @var int
	 */
	public const MESSAGE_ACK = 1;

	/**
	 * @var int
	 */
	public const MESSAGE_NACK = 2;

	/**
	 * @var int
	 */
	public const MESSAGE_REJECT = 3;

	/**
	 * @var int
	 */
	private $consumedMessages = 0;

	abstract public function getQueueName(): string;

	abstract public function process(Message $message): int;

	public function getConsumerTag(): string
	{
		return '';
	}

	public function isNoLocal(): bool
	{
		return false;
	}

	public function isNoAck(): bool
	{
		return false;
	}

	public function isExclusive(): bool
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

	public function getPrefetchSize(): int
	{
		return 0;
	}

	public function getPrefetchCount(): int
	{
		return 1;
	}

	/**
	 * @return MethodBasicConsumeOkFrame|PromiseInterface
	 */
	final public function consume(Channel $channel, ?int $numberOfMessages = null)
	{
		$channel->qos($this->getPrefetchSize(), $this->getPrefetchCount());

		return $channel->consume(
			function (Message $message, Channel $channel, $client) use ($numberOfMessages): void {
				$result = $this->process($message);

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
					default:
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
}
