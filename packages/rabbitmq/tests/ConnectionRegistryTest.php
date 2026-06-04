<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\ConnectionRegistry;

final class ConnectionRegistryTest extends TestCase
{
	/**
	 * @var BunnyManager
	 */
	private $defaultManager;

	/**
	 * @var BunnyManager
	 */
	private $secondaryManager;

	/**
	 * @var ConnectionRegistry
	 */
	private $connectionRegistry;


	protected function setUp(): void
	{
		parent::setUp();

		$this->defaultManager = $this->createBunnyManager();
		$this->secondaryManager = $this->createBunnyManager();
		$this->connectionRegistry = new ConnectionRegistry(
			[
				'default' => $this->defaultManager,
				'secondary' => $this->secondaryManager,
			],
			'default'
		);
	}


	public function testGetReturnsRequestedManager(): void
	{
		self::assertSame($this->defaultManager, $this->connectionRegistry->get('default'));
		self::assertSame($this->secondaryManager, $this->connectionRegistry->get('secondary'));
	}


	public function testGetWithNullReturnsDefault(): void
	{
		self::assertSame($this->defaultManager, $this->connectionRegistry->get());
		self::assertSame($this->defaultManager, $this->connectionRegistry->get(null));
	}


	public function testGetUnknownConnectionThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('RabbitMQ connection "nonExisting" does not exist.');

		$this->connectionRegistry->get('nonExisting');
	}


	public function testHas(): void
	{
		self::assertTrue($this->connectionRegistry->has('default'));
		self::assertTrue($this->connectionRegistry->has('secondary'));
		self::assertFalse($this->connectionRegistry->has('nonExisting'));
	}


	public function testGetDefaultConnectionName(): void
	{
		self::assertSame('default', $this->connectionRegistry->getDefaultConnectionName());
	}


	public function testGetNames(): void
	{
		self::assertSame(['default', 'secondary'], $this->connectionRegistry->getNames());
	}


	public function testAll(): void
	{
		self::assertSame(
			[
				'default' => $this->defaultManager,
				'secondary' => $this->secondaryManager,
			],
			$this->connectionRegistry->all()
		);
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
			[],
			[],
			[],
			[]
		);
	}

}
