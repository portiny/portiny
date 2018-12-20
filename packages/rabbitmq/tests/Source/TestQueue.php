<?php
declare(strict_types=1);

namespace Portiny\RabbitMQ\Tests\Source;

use Portiny\RabbitMQ\Queue\AbstractQueue;

final class TestQueue extends AbstractQueue
{
	public function getName(): string
	{
		return 'queueName';
	}
}
