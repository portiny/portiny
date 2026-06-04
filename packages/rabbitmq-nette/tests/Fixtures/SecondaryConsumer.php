<?php declare(strict_types = 1);

namespace Portiny\RabbitMQNette\Tests\Fixtures;

use Bunny\Message;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;

final class SecondaryConsumer extends AbstractConsumer
{

	public function getConnectionName(): string
	{
		return 'secondary';
	}


	protected function process(Message $message): int
	{
		return self::MESSAGE_ACK;
	}


	protected function getQueueName(): string
	{
		return 'secondary_queue';
	}

}
