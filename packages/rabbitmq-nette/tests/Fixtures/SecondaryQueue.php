<?php declare(strict_types = 1);

namespace Portiny\RabbitMQNette\Tests\Fixtures;

use Portiny\RabbitMQ\Queue\AbstractQueue;

final class SecondaryQueue extends AbstractQueue
{

	public function getConnectionName(): string
	{
		return 'secondary';
	}


	protected function getName(): string
	{
		return 'secondary_queue';
	}

}
