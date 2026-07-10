<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Client;

use Bunny\Channel;
use Bunny\Exception\ClientException;
use Bunny\Message;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Client\KeepaliveClient;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;
use Portiny\RabbitMQ\Producer\AbstractProducer;

/**
 * Real-broker contract of the server-initiated channel.close handling.
 *
 * Skipped unless RABBITMQ_IT_HOST is set (same convention as KeepaliveClientIntegrationTest).
 *
 * These tests pin the production incident of 2026-07-09: a queue.declare raised a channel
 * exception (NOT_FOUND on a classic delay queue whose home node was rebooting), the handler
 * swallowed it, and the subsequent basic.reject written to the server-closed channel escalated
 * to a connection-level "CHANNEL_ERROR - expected 'channel.open'" — requeueing the delivery and
 * looping. The fixed client must (a) mark the channel dead, (b) hand out a working replacement
 * channel on the same connection, and (c) skip the acknowledgement instead of poisoning the
 * connection when the consumed-on channel died mid-handler.
 */
final class ChannelClosedByServerIntegrationTest extends TestCase
{

	/**
	 * @return array<string, mixed>
	 */
	private function connectionOptions(): array
	{
		$host = getenv('RABBITMQ_IT_HOST');
		if ($host === false || $host === '') {
			self::markTestSkipped('Set RABBITMQ_IT_HOST to run the broker integration tests.');
		}

		return [
			'host' => $host,
			'port' => (int) (getenv('RABBITMQ_IT_PORT') ?: 5672),
			'user' => getenv('RABBITMQ_IT_USER') ?: 'guest',
			'password' => getenv('RABBITMQ_IT_PASS') ?: 'guest',
			'vhost' => getenv('RABBITMQ_IT_VHOST') ?: '/',
			'heartbeat' => 30.0,
			'timeout' => 5.0,
		];
	}


	private function connect(): KeepaliveClient
	{
		$client = new KeepaliveClient($this->connectionOptions());
		try {
			$client->connect();
		} catch (\Throwable $exception) {
			self::markTestSkipped('Broker unreachable: ' . $exception->getMessage());
		}

		return $client;
	}


	/**
	 * A channel exception must mark the channel dead, keep the CONNECTION alive, and a fresh
	 * channel() call (which reuses the freed channel id with a proper channel.open handshake)
	 * must be fully usable.
	 */
	public function testChannelExceptionMarksChannelDeadAndFreshChannelWorks(): void
	{
		$client = $this->connect();

		/** @var Channel $channel */
		$channel = $client->channel();

		try {
			// Passive declare of a queue that does not exist -> NOT_FOUND channel exception.
			$channel->queueDeclare('portiny_it_missing_' . getmypid(), true);
			self::fail('Passive declare of a missing queue must raise a channel exception.');
		} catch (ClientException $exception) {
			self::assertStringContainsString('NOT_FOUND', $exception->getMessage());
		}

		self::assertTrue($client->isChannelClosedByServer($channel));

		/** @var Channel $freshChannel */
		$freshChannel = $client->channel();
		self::assertFalse($client->isChannelClosedByServer($freshChannel));

		// The fresh channel must actually work against the broker (this would previously fail:
		// bunny kept the dead channel and any further frame killed the whole connection).
		$queue = 'portiny_it_recovery_' . getmypid();
		$freshChannel->queueDeclare($queue, false, false, false, true);
		$freshChannel->queueDelete($queue);

		$client->disconnect();
	}


	/**
	 * The production incident end-to-end: the handler triggers and swallows a channel
	 * exception, so the consume callback must SKIP the acknowledgement (throwing a transport
	 * error for the reconnect loop) instead of writing it to the dead channel — the connection
	 * must survive and the delivery must be redeliverable on a fresh channel.
	 */
	public function testAcknowledgementIsSkippedWhenHandlerKilledTheChannel(): void
	{
		$client = $this->connect();
		$queue = 'portiny_it_poison_' . getmypid();

		/** @var Channel $setupChannel */
		$setupChannel = $client->channel();
		$setupChannel->queueDeclare($queue, false, true, false, false);
		$setupChannel->publish('poison-payload', [], '', $queue);

		/** @var Channel $consumeChannel */
		$consumeChannel = $client->channel();

		$consumer = new class($consumeChannel) extends AbstractConsumer {
			public function __construct(private readonly Channel $channel)
			{
			}

			protected function process(Message $message): int
			{
				try {
					// Same shape as a producer declaring a delay queue inside the handler while
					// the queue is unavailable: channel exception, swallowed by the caller.
					$this->channel->queueDeclare('portiny_it_missing_' . getmypid(), true);
				} catch (ClientException $ignored) {
				}

				return self::MESSAGE_ACK;
			}

			protected function getQueueName(): string
			{
				return 'portiny_it_poison_' . getmypid();
			}
		};

		$consumer->consume($consumeChannel, 1);

		try {
			$client->run(5);
			self::fail('The consume callback must surface a transport error for the dead channel.');
		} catch (ClientException $exception) {
			self::assertStringContainsString('closed by the server', $exception->getMessage());
		}

		// The connection survived (no basic.reject was written to the dead channel) and the
		// delivery is redeliverable on a fresh channel.
		self::assertTrue($client->isConnected());

		/** @var Channel $redeliveryChannel */
		$redeliveryChannel = $client->channel();
		$redelivered = $redeliveryChannel->get($queue);

		self::assertInstanceOf(Message::class, $redelivered);
		self::assertSame('poison-payload', $redelivered->content);
		self::assertTrue($redelivered->redelivered);

		$redeliveryChannel->ack($redelivered);
		$redeliveryChannel->queueDelete($queue);

		$client->disconnect();
	}


	/**
	 * produceWithDelay() against a real broker: the quorum holding queue is accepted by the
	 * broker (x-queue-type + TTL + expiry + DLX arguments together) and the message is
	 * dead-lettered into the target queue after the delay.
	 */
	public function testProduceWithDelayDeliversViaQuorumHoldingQueue(): void
	{
		$client = $this->connect();
		$suffix = (string) getmypid();
		$exchange = 'portiny_it_delay_ex_' . $suffix;
		$routingKey = 'portiny_it_delay_rk_' . $suffix;
		$queue = 'portiny_it_delay_target_' . $suffix;

		/** @var Channel $channel */
		$channel = $client->channel();
		$channel->exchangeDeclare($exchange, 'direct', false, false, true);
		$channel->queueDeclare($queue, false, true, false, false);
		$channel->queueBind($queue, $exchange, $routingKey);
		// The static delays exchange normally declared by BunnyManager::declareExchanges().
		$channel->exchangeDeclare('delays', 'direct', false, true, false);

		$producer = new class($exchange, $routingKey) extends AbstractProducer {
			public function __construct(
				private readonly string $exchange,
				private readonly string $routingKey
			) {
			}

			protected function getExchangeName(): string
			{
				return $this->exchange;
			}

			protected function getRoutingKey(): string
			{
				return $this->routingKey;
			}
		};

		$producer->produceWithDelay($channel, 'delayed-payload', 1000);

		// Immediately after the publish the message must still be in the holding queue.
		self::assertNull($channel->get($queue));

		// After the TTL it must have been dead-lettered into the target queue.
		$delivered = null;
		foreach (range(1, 40) as $attempt) {
			usleep(250_000);
			$delivered = $channel->get($queue);
			if ($delivered instanceof Message) {
				break;
			}
		}

		self::assertInstanceOf(Message::class, $delivered);
		self::assertSame('delayed-payload', $delivered->content);
		$channel->ack($delivered);

		$channel->queueDelete($queue);
		$channel->exchangeDelete($exchange);

		$client->disconnect();
	}

}
