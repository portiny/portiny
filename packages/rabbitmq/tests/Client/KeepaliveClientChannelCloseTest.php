<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Client;

use Bunny\Channel;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Tests\Source\TestKeepaliveClient;

/**
 * Server-initiated channel.close bookkeeping in KeepaliveClient.
 *
 * Bunny calls channelCloseOk() exactly (and only) when acknowledging a channel.close frame
 * received FROM the broker; the tests below simulate that call directly and verify the client
 * marks the Channel object dead and removes it from the registry so its id can be reused.
 */
final class KeepaliveClientChannelCloseTest extends TestCase
{

	public function testServerInitiatedCloseMarksChannelDeadAndRemovesIt(): void
	{
		$client = new TestKeepaliveClient(['host' => '127.0.0.10', 'port' => 9999]);
		$channel = new Channel($client, 1);
		$client->registerChannelForTest(1, $channel);

		self::assertFalse($client->isChannelClosedByServer($channel));

		$client->channelCloseOk(1);

		self::assertTrue($client->isChannelClosedByServer($channel));
		self::assertFalse($client->hasChannelForTest(1));
	}


	public function testCloseOkForUnknownChannelIdIsIgnored(): void
	{
		$client = new TestKeepaliveClient(['host' => '127.0.0.10', 'port' => 9999]);
		$channel = new Channel($client, 1);
		$client->registerChannelForTest(1, $channel);

		$client->channelCloseOk(42);

		self::assertFalse($client->isChannelClosedByServer($channel));
		self::assertTrue($client->hasChannelForTest(1));
	}


	public function testFreshChannelObjectWithReusedIdIsNotDead(): void
	{
		$client = new TestKeepaliveClient(['host' => '127.0.0.10', 'port' => 9999]);
		$oldChannel = new Channel($client, 1);
		$client->registerChannelForTest(1, $oldChannel);
		$client->channelCloseOk(1);

		// A future channel() call may reuse the freed id 1 with a new Channel object and a
		// proper channel.open handshake — deadness is tracked per object, not per id.
		$newChannel = new Channel($client, 1);
		$client->registerChannelForTest(1, $newChannel);

		self::assertTrue($client->isChannelClosedByServer($oldChannel));
		self::assertFalse($client->isChannelClosedByServer($newChannel));
	}

}
