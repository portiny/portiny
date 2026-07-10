<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Consumer;

use Bunny\Channel;
use Bunny\Exception\ClientException;
use Bunny\Message;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;
use Portiny\RabbitMQ\Tests\Source\TestConsumer;
use Portiny\RabbitMQ\Tests\Source\TestKeepaliveClient;

/**
 * The consume callback must never write an ack/nack/reject to a channel the server has
 * closed while the handler was running — the broker has already requeued the delivery and
 * the write would escalate to a connection-level "CHANNEL_ERROR - expected 'channel.open'".
 */
final class AbstractConsumerDeadChannelTest extends TestCase
{

	public function testAckIsSentOnHealthyChannel(): void
	{
		$client = new TestKeepaliveClient(['host' => '127.0.0.10', 'port' => 9999]);
		[$channel, $callback] = $this->consumeAndCaptureCallback(new TestConsumer());

		$channel->expects(self::once())->method('ack');
		$channel->expects(self::never())->method('reject');

		$callback($this->createMessage(), $channel, $client);
	}


	public function testAckIsSkippedAndClientExceptionThrownWhenServerClosedTheChannel(): void
	{
		$client = new TestKeepaliveClient(['host' => '127.0.0.10', 'port' => 9999]);
		[$channel, $callback] = $this->consumeAndCaptureCallback(new TestConsumer());

		// Simulate bunny acknowledging a server-initiated channel.close for this channel
		// while the handler was running.
		$client->registerChannelForTest(1, $channel);
		$client->channelCloseOk(1);

		$channel->expects(self::never())->method('ack');
		$channel->expects(self::never())->method('reject');

		$this->expectException(ClientException::class);
		$this->expectExceptionMessage('closed by the server');

		$callback($this->createMessage(), $channel, $client);
	}


	public function testHandlerErrorOnHealthyChannelStillRejectsAndRethrows(): void
	{
		$client = new TestKeepaliveClient(['host' => '127.0.0.10', 'port' => 9999]);
		[$channel, $callback] = $this->consumeAndCaptureCallback($this->createThrowingConsumer(
			new \RuntimeException('handler failed')
		));

		$channel->expects(self::once())->method('reject');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('handler failed');

		$callback($this->createMessage(), $channel, $client);
	}


	public function testHandlerErrorOnDeadChannelSkipsRejectAndRethrowsOriginal(): void
	{
		$client = new TestKeepaliveClient(['host' => '127.0.0.10', 'port' => 9999]);
		[$channel, $callback] = $this->consumeAndCaptureCallback($this->createThrowingConsumer(
			new \RuntimeException('handler failed')
		));

		$client->registerChannelForTest(1, $channel);
		$client->channelCloseOk(1);

		$channel->expects(self::never())->method('reject');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('handler failed');

		$callback($this->createMessage(), $channel, $client);
	}


	public function testTransportErrorFromHandlerSkipsRejectEvenOnHealthyChannel(): void
	{
		$client = new TestKeepaliveClient(['host' => '127.0.0.10', 'port' => 9999]);
		[$channel, $callback] = $this->consumeAndCaptureCallback($this->createThrowingConsumer(
			new ClientException('Broken pipe or closed connection.')
		));

		$channel->expects(self::never())->method('reject');

		$this->expectException(ClientException::class);
		$this->expectExceptionMessage('Broken pipe or closed connection.');

		$callback($this->createMessage(), $channel, $client);
	}


	/**
	 * Run AbstractConsumer::consume() against a mocked channel and capture the delivery
	 * callback it registers, so tests can invoke it directly with a chosen client state.
	 *
	 * @return array{Channel&\PHPUnit\Framework\MockObject\MockObject, callable}
	 */
	private function consumeAndCaptureCallback(AbstractConsumer $consumer): array
	{
		/** @var Channel&\PHPUnit\Framework\MockObject\MockObject $channel */
		$channel = $this->createMock(Channel::class);
		$channel->method('getChannelId')->willReturn(1);

		$capturedCallback = null;
		$channel->method('consume')->willReturnCallback(
			static function (callable $callback) use (&$capturedCallback) {
				$capturedCallback = $callback;
				return null;
			}
		);

		$consumer->consume($channel);
		self::assertIsCallable($capturedCallback);

		return [$channel, $capturedCallback];
	}


	private function createThrowingConsumer(\Throwable $throwable): AbstractConsumer
	{
		return new class($throwable) extends AbstractConsumer {
			public function __construct(private readonly \Throwable $throwable)
			{
			}

			protected function process(Message $message): int
			{
				throw $this->throwable;
			}

			protected function getQueueName(): string
			{
				return 'queueName';
			}
		};
	}


	private function createMessage(): Message
	{
		return new Message('consumerTag', 1, false, 'exchange', 'routingKey', [], 'body');
	}

}
