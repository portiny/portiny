<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Source;

use Portiny\RabbitMQ\Producer\AbstractProducer;

final class TestProducer extends AbstractProducer
{

	protected function getExchangeName(): string
	{
		return 'exchangeName';
	}


	protected function getRoutingKey(): string
	{
		return 'routingKey';
	}

}
