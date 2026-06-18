<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Client;

use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Client\KeepaliveClient;
use function React\Promise\reject;
use function React\Promise\resolve;

/**
 * KeepaliveClient overrides Bunny\Client::__destruct() so a dying client can never crash the
 * worker. The parent destructor calls disconnect()->done() and then re-runs the loop; for a
 * broker-initiated close (a channel still open at close time) disconnect() rejects with a plain
 * \LogicException ("All channels have to be closed by now."), which the parent done() rethrows as
 * a FATAL uncaught error at GC / process exit — the exact flood seen during the nightly broker
 * maintenance window. The ConsumeCommand reconnect loop cannot catch it (it fires outside any
 * try/catch), so the fix lives here. These tests pin that the override consumes the rejection.
 */
final class KeepaliveClientDestructTest extends TestCase
{

	/**
	 * The headline case: a broker-initiated close left disconnect() rejected. The destructor must
	 * swallow it, not rethrow "All channels have to be closed by now." as a fatal error.
	 */
	public function testDestructSwallowsRejectedDisconnect(): void
	{
		$client = new class(['host' => '127.0.0.1', 'port' => 5672, 'user' => 'guest', 'password' => 'guest', 'vhost' => '/']) extends KeepaliveClient {
			public function isConnected()
			{
				return true;
			}

			public function disconnect($replyCode = 0, $replyText = '')
			{
				return reject(new \LogicException('All channels have to be closed by now.'));
			}
		};

		// Must not rethrow the rejected disconnect — reaching the assertion proves no fatal escaped.
		$client->__destruct();

		$this->addToAssertionCount(1);
	}


	/**
	 * disconnect() can return null (nothing to wait for); the parent destructor blows up with
	 * "Call to a member function done() on null". The override must tolerate it.
	 */
	public function testDestructToleratesNullDisconnect(): void
	{
		$client = new class(['host' => '127.0.0.1', 'port' => 5672, 'user' => 'guest', 'password' => 'guest', 'vhost' => '/']) extends KeepaliveClient {
			public function isConnected()
			{
				return true;
			}

			public function disconnect($replyCode = 0, $replyText = '')
			{
				return null;
			}
		};

		$client->__destruct();

		$this->addToAssertionCount(1);
	}


	/**
	 * When the client is already disconnected there is nothing to clean up — the destructor must be
	 * a no-op and never touch disconnect()/the loop.
	 */
	public function testDestructIsNoopWhenNotConnected(): void
	{
		$client = new class(['host' => '127.0.0.1', 'port' => 5672, 'user' => 'guest', 'password' => 'guest', 'vhost' => '/']) extends KeepaliveClient {
			public bool $disconnectCalled = false;

			public function isConnected()
			{
				return false;
			}

			public function disconnect($replyCode = 0, $replyText = '')
			{
				$this->disconnectCalled = true;

				return resolve(null);
			}
		};

		$client->__destruct();

		self::assertFalse($client->disconnectCalled, 'disconnect() must not run when the client is not connected');
	}


	/**
	 * No regression for the happy path: a still-connected client (a normal consumer/producer exiting
	 * cleanly) must STILL be disconnected gracefully — Bunny's synchronous disconnect() closes the
	 * channels and sends Connection.Close (flushing any pending ack). The override only stops the
	 * post-disconnect loop re-run and the fatal done() rethrow; it must not skip the disconnect.
	 */
	public function testDestructStillDisconnectsGracefullyWhenConnected(): void
	{
		$client = new class(['host' => '127.0.0.1', 'port' => 5672, 'user' => 'guest', 'password' => 'guest', 'vhost' => '/']) extends KeepaliveClient {
			public bool $disconnectCalled = false;

			public function isConnected()
			{
				return true;
			}

			public function disconnect($replyCode = 0, $replyText = '')
			{
				$this->disconnectCalled = true;

				return resolve(null);
			}
		};

		$client->__destruct();

		self::assertTrue($client->disconnectCalled, 'a connected client must still disconnect gracefully on teardown');
	}

}
