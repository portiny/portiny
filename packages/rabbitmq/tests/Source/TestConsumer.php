<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Source;

use Bunny\Message;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;

final class TestConsumer extends AbstractConsumer
{

	public function process(Message $message): int
	{
		return self::MESSAGE_ACK;
	}


	protected function getQueueName(): string
	{
		return 'queueName';
	}

}
