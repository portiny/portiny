<?php declare(strict_types = 1);

namespace Portiny\RabbitMQNette\Tests\DI;

use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\ConnectionRegistry;
use Portiny\RabbitMQNette\Tests\AbstractContainerTestCase;

final class ConsoleExtensionTest extends AbstractContainerTestCase
{

	public function testLoadConfiguration(): void
	{
		$connectionRegistry = $this->container->getByType(ConnectionRegistry::class);
		self::assertInstanceOf(ConnectionRegistry::class, $connectionRegistry);
	}


	public function testDefaultConnectionExistsWithoutConfiguration(): void
	{
		/** @var ConnectionRegistry $connectionRegistry */
		$connectionRegistry = $this->container->getByType(ConnectionRegistry::class);

		self::assertSame('default', $connectionRegistry->getDefaultConnectionName());
		self::assertTrue($connectionRegistry->has('default'));
		self::assertInstanceOf(BunnyManager::class, $connectionRegistry->get());
		self::assertInstanceOf(BunnyManager::class, $connectionRegistry->get('default'));
	}

}
