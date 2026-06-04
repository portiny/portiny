<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Producer;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\ConnectionRegistry;
use Portiny\RabbitMQ\Producer\Producer;
use Portiny\RabbitMQ\Tests\Source\SecondaryConnectionProducer;
use Portiny\RabbitMQ\Tests\Source\TestProducer;

final class ProducerTest extends TestCase
{
	/**
	 * @var Producer
	 */
	private $producer;


	protected function setUp(): void
	{
		parent::setUp();

		$this->producer = new Producer(new ConnectionRegistry(
			['default' => $this->createBunnyManager()],
			'default'
		));
	}


	public function testProduceUsesProducerConnectionName(): void
	{
		// The TestProducer reports the "default" connection (registered),
		// while SecondaryConnectionProducer reports "secondary" (not registered),
		// which proves the producer connection name drives the manager selection.
		self::assertSame('default', (new TestProducer())->getConnectionName());
		self::assertSame('secondary', (new SecondaryConnectionProducer())->getConnectionName());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('RabbitMQ connection "secondary" does not exist.');

		$this->producer->produce(new SecondaryConnectionProducer(), 'body');
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
