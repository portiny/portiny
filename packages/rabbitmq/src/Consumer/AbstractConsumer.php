<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Consumer;

use Bunny\Async\Client as AsyncClient;
use Bunny\Channel;
use Bunny\Client;
use Bunny\Exception\ClientException;
use Bunny\Message;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use InvalidArgumentException;
use Portiny\RabbitMQ\Client\KeepaliveClient;
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
					// Requeue is best-effort only: when the failure is a transport error, or the
					// server has closed this channel in the meantime (e.g. a queue.declare issued
					// by the handler raised a channel exception), the broker requeues the delivery
					// on its own and writing basic.reject to the dead channel would be a protocol
					// violation escalating to a connection-level CHANNEL_ERROR.
					if (! $throwable instanceof ClientException && ! $this->isChannelClosedByServer($channel, $client)) {
						$channel->reject($message);
					}
					throw $throwable;
				}

				$this->assertChannelIsUsable($channel, $client);

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


	/**
	 * Refuse to send the message acknowledgement when the server closed the consuming channel
	 * while the handler was running (a channel exception raised by anything the handler did —
	 * typically a failed queue.declare from a producer sharing the channel).
	 *
	 * On a server-initiated channel.close the broker has already discarded the channel state
	 * and requeued every unacked delivery, so the ack/reject can no longer be honoured; sending
	 * it anyway is a protocol violation that escalates to a connection-level
	 * "CHANNEL_ERROR - expected 'channel.open'". Throwing a ClientException here instead lets
	 * the ConsumeCommand reconnect loop rebuild clean handles and the requeued delivery arrive
	 * again on a healthy channel.
	 */
	private function assertChannelIsUsable(Channel $channel, $client): void
	{
		if ($this->isChannelClosedByServer($channel, $client)) {
			throw new ClientException(sprintf(
				'Channel #%d was closed by the server while the message was being processed; '
				. 'skipping the acknowledgement — the broker has already requeued the delivery.',
				$channel->getChannelId()
			));
		}
	}


	/**
	 * @param Client|AsyncClient $client
	 */
	private function isChannelClosedByServer(Channel $channel, $client): bool
	{
		return $client instanceof KeepaliveClient && $client->isChannelClosedByServer($channel);
	}


	/**
	 * Name of the RabbitMQ connection this consumer belongs to.
	 *
	 * Must return a constant value independent of the object state, as integrations read it via reflection
	 * to assign the component to a connection.
	 */
	public function getConnectionName(): string
	{
		return 'default';
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
