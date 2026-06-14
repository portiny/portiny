<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests;

use Bunny\Channel;
use Bunny\Client;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Tests\Source\TestConsumer;

/**
 * Tests for BunnyManager::reconnect().
 *
 * These tests verify that reconnect() tears down the memoized client/channel state
 * so that the next getClient()/getChannel() call rebuilds fresh handles. No live
 * broker is required because we inject pre-built mock doubles directly into the
 * BunnyManager properties via Reflection.
 */
final class BunnyManagerReconnectTest extends TestCase
{

	/**
	 * @var BunnyManager
	 */
	private $bunnyManager;


	protected function setUp(): void
	{
		parent::setUp();

		$this->bunnyManager = $this->createBunnyManager();
	}


	public function testReconnectNullsClientAndChannel(): void
	{
		/** @var Client&\PHPUnit\Framework\MockObject\MockObject $mockClient */
		$mockClient = $this->createMock(Client::class);
		$mockClient->method('isConnected')->willReturn(false);

		/** @var Channel&\PHPUnit\Framework\MockObject\MockObject $mockChannel */
		$mockChannel = $this->createMock(Channel::class);

		$this->injectProperty($this->bunnyManager, 'client', $mockClient);
		$this->injectProperty($this->bunnyManager, 'channel', $mockChannel);
		$this->injectProperty($this->bunnyManager, 'isDeclared', true);

		$this->bunnyManager->reconnect();

		self::assertNull($this->readProperty($this->bunnyManager, 'client'));
		self::assertNull($this->readProperty($this->bunnyManager, 'channel'));
		self::assertFalse($this->readProperty($this->bunnyManager, 'isDeclared'));
	}


	public function testReconnectDisconnectsConnectedClientBeforeNulling(): void
	{
		/** @var Client&\PHPUnit\Framework\MockObject\MockObject $mockClient */
		$mockClient = $this->createMock(Client::class);
		$mockClient->method('isConnected')->willReturn(true);
		// disconnect() must be called exactly once.
		$mockClient->expects(self::once())->method('disconnect');

		$this->injectProperty($this->bunnyManager, 'client', $mockClient);

		$this->bunnyManager->reconnect();

		self::assertNull($this->readProperty($this->bunnyManager, 'client'));
	}


	public function testReconnectSwallowsDisconnectException(): void
	{
		/** @var Client&\PHPUnit\Framework\MockObject\MockObject $mockClient */
		$mockClient = $this->createMock(Client::class);
		$mockClient->method('isConnected')->willReturn(true);
		$mockClient->method('disconnect')->willThrowException(new \RuntimeException('Broken pipe'));

		$this->injectProperty($this->bunnyManager, 'client', $mockClient);

		// Must not throw; the connection was already dead.
		$this->bunnyManager->reconnect();

		self::assertNull($this->readProperty($this->bunnyManager, 'client'));
		self::assertFalse($this->readProperty($this->bunnyManager, 'isDeclared'));
	}


	public function testReconnectOnNullClientSkipsDisconnect(): void
	{
		// Start state: no client was ever created.
		self::assertNull($this->readProperty($this->bunnyManager, 'client'));

		// Must not throw.
		$this->bunnyManager->reconnect();

		self::assertNull($this->readProperty($this->bunnyManager, 'client'));
		self::assertNull($this->readProperty($this->bunnyManager, 'channel'));
		self::assertFalse($this->readProperty($this->bunnyManager, 'isDeclared'));
	}


	public function testReconnectResetsIsDeclaredFlag(): void
	{
		$this->injectProperty($this->bunnyManager, 'isDeclared', true);

		$this->bunnyManager->reconnect();

		self::assertFalse($this->readProperty($this->bunnyManager, 'isDeclared'));
	}


	private function createBunnyManager(): BunnyManager
	{
		return new BunnyManager(
			[
				'host' => '127.0.0.10',
				'port' => 9999,
				'user' => 'guest',
				'password' => 'guest',
				'vhost' => '/',
				'timeout' => 1,
				'heartbeat' => 60.0,
				'persistent' => false,
				'path' => '/',
				'tcp_nodelay' => false,
			],
			[
				'myAlias' => 'Portiny\\RabbitMQ\\Tests\\Source\\TestConsumer',
			],
			[new TestConsumer()],
			[],
			[]
		);
	}


	/**
	 * @param mixed $value
	 */
	private function injectProperty(BunnyManager $object, string $property, $value): void
	{
		$ref = new \ReflectionProperty($object, $property);
		$ref->setAccessible(true);
		$ref->setValue($object, $value);
	}


	/**
	 * @return mixed
	 */
	private function readProperty(BunnyManager $object, string $property)
	{
		$ref = new \ReflectionProperty($object, $property);
		$ref->setAccessible(true);
		return $ref->getValue($object);
	}

}
