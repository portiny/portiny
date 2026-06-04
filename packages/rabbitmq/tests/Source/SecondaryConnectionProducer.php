<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Source;

use Portiny\RabbitMQ\Producer\AbstractProducer;

final class SecondaryConnectionProducer extends AbstractProducer
{

	public function getConnectionName(): string
	{
		return 'secondary';
	}


	protected function getExchangeName(): string
	{
		return 'secondaryExchangeName';
	}


	protected function getRoutingKey(): string
	{
		return 'secondaryRoutingKey';
	}

}
