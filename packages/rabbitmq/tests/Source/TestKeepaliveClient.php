<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Source;

use Bunny\Channel;
use Portiny\RabbitMQ\Client\KeepaliveClient;

/**
 * KeepaliveClient test double that never touches the network: outgoing frames are dropped,
 * the connection always reports connected, and channel() can be pre-seeded with a fixed
 * Channel instance. The channel registry is exposed so tests can simulate the exact state
 * bunny is in when a server-initiated channel.close arrives.
 */
final class TestKeepaliveClient extends KeepaliveClient
{
	/**
	 * @var Channel|null
	 */
	public $nextChannel;


	public function isConnected()
	{
		return true;
	}


	/**
	 * @return Channel
	 */
	public function channel()
	{
		return $this->nextChannel ?? parent::channel();
	}


	public function registerChannelForTest(int $channelId, Channel $channel): void
	{
		$this->channels[$channelId] = $channel;
	}


	public function hasChannelForTest(int $channelId): bool
	{
		return isset($this->channels[$channelId]);
	}


	protected function flushWriteBuffer()
	{
		return true;
	}

}
