<?php
declare(strict_types=1);

namespace Portiny\RabbitMQ\Tests\Source;

use Portiny\RabbitMQ\Exchange\AbstractExchange;

final class TestExchange extends AbstractExchange
{
	public function getName(): string
	{
		return 'exchangeName';
	}
}
