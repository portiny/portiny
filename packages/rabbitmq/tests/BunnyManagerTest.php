<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests;

use Bunny\Async\Client as AsyncClient;
use Bunny\Client;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Tests\Source\TestConsumer;
use React\EventLoop\Factory;

final class BunnyManagerTest extends TestCase
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


	public function testGetClient(): void
	{
		$client = $this->bunnyManager->getClient();

		self::assertInstanceOf(Client::class, $client);
		self::assertSame($client, $this->bunnyManager->getClient());
	}


	public function testGetAsyncClient(): void
	{
		$loop = Factory::create();
		$this->bunnyManager->setLoop($loop);

		$client = $this->bunnyManager->getClient();

		self::assertInstanceOf(AsyncClient::class, $client);
		self::assertSame($client, $this->bunnyManager->getClient());
	}


	public function testGetClassNameByAlias(): void
	{
		self::assertInstanceOf(TestConsumer::class, $this->bunnyManager->getConsumerByAlias('myAlias'));
		self::assertNull($this->bunnyManager->getConsumerByAlias('nonExisting'));
	}


	protected function createBunnyManager(): BunnyManager
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

}
