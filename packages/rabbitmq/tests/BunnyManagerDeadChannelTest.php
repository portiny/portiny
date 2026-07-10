<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests;

use Bunny\Channel;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Tests\Source\TestKeepaliveClient;

/**
 * BunnyManager::getChannel() must not keep handing out a memoized channel that the server
 * has closed (producers resolve their channel through the manager on every publish, so
 * without this self-heal every publish after a channel exception would be silently written
 * to a dead channel).
 */
final class BunnyManagerDeadChannelTest extends TestCase
{

	public function testMemoizedChannelClosedByServerIsReplacedByFreshOne(): void
	{
		$client = new TestKeepaliveClient(['host' => '127.0.0.10', 'port' => 9999]);

		$deadChannel = new Channel($client, 1);
		$client->registerChannelForTest(1, $deadChannel);

		$freshChannel = new Channel($client, 1);
		$client->nextChannel = $freshChannel;

		$manager = $this->createManager();
		$this->injectProperty($manager, 'client', $client);
		$this->injectProperty($manager, 'channel', $deadChannel);

		// Healthy channel stays memoized.
		self::assertSame($deadChannel, $manager->getChannel());

		// Simulate bunny acknowledging a server-initiated channel.close.
		$client->channelCloseOk(1);

		self::assertSame($freshChannel, $manager->getChannel());
		// And the fresh handle is memoized from now on.
		self::assertSame($freshChannel, $manager->getChannel());
	}


	private function createManager(): BunnyManager
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


	private function injectProperty(BunnyManager $manager, string $property, $value): void
	{
		$reflection = new \ReflectionProperty(BunnyManager::class, $property);
		$reflection->setAccessible(true);
		$reflection->setValue($manager, $value);
	}

}
