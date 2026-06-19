<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Client;

use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Client\KeepaliveClient;
use function React\Promise\reject;
use function React\Promise\resolve;

/**
 * KeepaliveClient overrides Bunny\Client::__destruct() so a dying client can never crash the
 * worker, while still tearing the connection down gracefully.
 *
 * Two properties must hold and are pinned here:
 *  - SAFETY: a broker-initiated close leaves disconnect() rejected with a \LogicException ("All
 *    channels have to be closed by now.") / returning null; the override must consume that and
 *    never rethrow it as a fatal uncaught error (the parent done() does rethrow it).
 *  - NO MESSAGE LOSS: on a healthy connection the override MUST still pump the loop with run(), the
 *    step that completes the Channel.Close/Connection.Close handshake and flushes in-flight
 *    publishes. Dropping that pump tears the TCP connection down abruptly and the broker loses
 *    just-published messages — so `testDestructDisconnectsAndPumpsLoopWhenConnected` guards it.
 *
 * The doubles override isConnected()/disconnect()/run() so no live broker is required; the real
 * end-to-end behaviour is covered by KeepaliveClientIntegrationTest (skipped without a broker).
 */
final class KeepaliveClientDestructTest extends TestCase
{

	/**
	 * @param mixed $disconnectResult value returned by the stubbed disconnect()
	 */
	private function makeClient(bool $connected, $disconnectResult): KeepaliveClient
	{
		return new class(['host' => '127.0.0.1', 'port' => 5672, 'user' => 'guest', 'password' => 'guest', 'vhost' => '/'], $connected, $disconnectResult) extends KeepaliveClient {
			/** @var bool */
			public $disconnectCalled = false;

			/** @var bool */
			public $ranLoop = false;

			/** @var bool */
			private $stubConnected;

			/** @var mixed */
			private $disconnectResult;

			public function __construct(array $options, bool $stubConnected, $disconnectResult)
			{
				parent::__construct($options);
				$this->stubConnected = $stubConnected;
				$this->disconnectResult = $disconnectResult;
			}

			public function isConnected()
			{
				return $this->stubConnected;
			}

			public function disconnect($replyCode = 0, $replyText = '')
			{
				$this->disconnectCalled = true;

				return $this->disconnectResult;
			}

			public function run($maxSeconds = null)
			{
				// Record the pump without touching a (non-existent) socket.
				$this->ranLoop = true;
			}
		};
	}


	/**
	 * The headline safety case: a broker-initiated close left disconnect() rejected. The destructor
	 * must swallow it, not rethrow "All channels have to be closed by now." as a fatal error.
	 */
	public function testDestructSwallowsRejectedDisconnect(): void
	{
		$client = $this->makeClient(true, reject(new \LogicException('All channels have to be closed by now.')));

		// Reaching the assertion proves no fatal escaped the destructor.
		$client->__destruct();

		self::assertTrue($client->disconnectCalled);
	}


	/**
	 * disconnect() can return null (nothing to wait for); the parent destructor blows up with
	 * "Call to a member function done() on null". The override must tolerate it.
	 */
	public function testDestructToleratesNullDisconnect(): void
	{
		$client = $this->makeClient(true, null);

		$client->__destruct();

		self::assertTrue($client->disconnectCalled);
		self::assertTrue($client->ranLoop, 'run() must still be pumped when disconnect() returns null');
	}


	/**
	 * When the client is already disconnected there is nothing to clean up — the destructor must be
	 * a no-op: neither disconnect() nor the run() pump may be touched.
	 */
	public function testDestructIsNoopWhenNotConnected(): void
	{
		$client = $this->makeClient(false, resolve(null));

		$client->__destruct();

		self::assertFalse($client->disconnectCalled, 'disconnect() must not run when the client is not connected');
		self::assertFalse($client->ranLoop, 'run() must not be pumped when the client is not connected');
	}


	/**
	 * NO MESSAGE LOSS regression guard: a still-connected client (a normal producer/consumer exiting
	 * cleanly) must disconnect AND pump the loop with run(). The run() pump is what completes the
	 * Connection.Close handshake and flushes in-flight publishes; dropping it loses messages.
	 */
	public function testDestructDisconnectsAndPumpsLoopWhenConnected(): void
	{
		$client = $this->makeClient(true, resolve(null));

		$client->__destruct();

		self::assertTrue($client->disconnectCalled, 'a connected client must disconnect gracefully on teardown');
		self::assertTrue($client->ranLoop, 'a connected client MUST pump run() so the clean close completes and no publish is lost');
	}

}
