<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Client;

use Bunny\Message;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Client\KeepaliveClient;

/**
 * End-to-end teardown behaviour against a real broker.
 *
 * Skipped unless RABBITMQ_IT_HOST is set (so CI without a broker stays green). Point the env vars
 * at any reachable broker, e.g.:
 *
 *   RABBITMQ_IT_HOST=rabbitmq RABBITMQ_IT_PORT=5672 RABBITMQ_IT_VHOST=/ \
 *   RABBITMQ_IT_USER=guest RABBITMQ_IT_PASS=guest \
 *     vendor/bin/phpunit packages/rabbitmq/tests/Client/KeepaliveClientIntegrationTest.php
 *
 * These tests pin the real-broker contract behind the unit-level run()-pump guard: the corrected
 * destructor completes a clean disconnect and the published message survives, and they document
 * why the pump is required (disconnect() alone leaves the connection un-closed).
 */
final class KeepaliveClientIntegrationTest extends TestCase
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


	private function connect(array $options): KeepaliveClient
	{
		$client = new KeepaliveClient($options);
		try {
			$client->connect();
		} catch (\Throwable $exception) {
			self::markTestSkipped('Broker unreachable: ' . $exception->getMessage());
		}

		return $client;
	}


	/**
	 * The corrected destructor must complete a clean disconnect (the run() pump finishes the
	 * Connection.Close handshake) AND the message published just before teardown must survive.
	 */
	public function testCleanTeardownDeliversPublishedMessageAndFullyDisconnects(): void
	{
		$options = $this->connectionOptions();
		$queue = 'portiny_keepalive_it_' . getmypid();
		$payload = 'survives-teardown-' . $queue;

		$publisher = $this->connect($options);
		$channel = $publisher->channel();
		$channel->queueDeclare($queue, false, true, false, false);
		$channel->publish($payload, [], '', $queue);

		self::assertTrue($publisher->isConnected());

		// Teardown through the destructor: it must pump run() so the close completes cleanly.
		$publisher->__destruct();

		self::assertFalse(
			$publisher->isConnected(),
			'corrected destructor must complete a clean disconnect (run() pump), not leave it half-closed'
		);

		$reader = $this->connect($options);
		$readChannel = $reader->channel();
		$message = $readChannel->get($queue, true);

		self::assertInstanceOf(
			Message::class,
			$message,
			'the message published right before a clean teardown must not be lost'
		);
		self::assertSame($payload, $message->content);

		$readChannel->queueDelete($queue);
		// Let the (corrected) destructor close the reader cleanly when it goes out of scope.
	}


	/**
	 * Documents the root cause of the message loss: disconnect() WITHOUT the run() pump does not
	 * complete the close — the client stays in the DISCONNECTING state (isConnected() still true),
	 * so the socket would be torn down abruptly at GC and the broker can drop in-flight publishes.
	 * This is exactly the pump the corrected destructor keeps.
	 */
	public function testDisconnectWithoutPumpDoesNotCompleteTheClose(): void
	{
		$options = $this->connectionOptions();
		$queue = 'portiny_keepalive_it_nopump_' . getmypid();

		$client = $this->connect($options);
		$channel = $client->channel();
		$channel->queueDeclare($queue, false, true, false, false);
		$channel->publish('x', [], '', $queue);

		// disconnect() alone, no run() pump — mirrors the broken teardown.
		$client->disconnect();

		self::assertTrue(
			$client->isConnected(),
			'without the run() pump the close never completes — the connection is left half-open'
		);

		// Clean up via a fresh client (and let the corrected destructor finish both at GC).
		$cleanup = $this->connect($options);
		$cleanup->channel()->queueDelete($queue);
	}

}
