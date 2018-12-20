<?php
declare(strict_types=1);

namespace Portiny\RabbitMQ\Tests\Source;

use Portiny\RabbitMQ\Producer\AbstractProducer;

final class TestProducer extends AbstractProducer
{
	public function getExchangeName(): string
	{
		return 'exchangeName';
	}

	public function getRoutingKey(): string
	{
		return 'routingKey';
	}
}
