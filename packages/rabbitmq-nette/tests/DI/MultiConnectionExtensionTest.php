<?php declare(strict_types = 1);

namespace Portiny\RabbitMQNette\Tests\DI;

use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\ConnectionRegistry;
use Portiny\RabbitMQNette\Tests\ContainerFactory;

final class MultiConnectionExtensionTest extends TestCase
{

	public function testMultipleConnectionsAreRegistered(): void
	{
		$container = ContainerFactory::create(__DIR__ . '/../config/multiConnection.neon');

		/** @var ConnectionRegistry $connectionRegistry */
		$connectionRegistry = $container->getByType(ConnectionRegistry::class);

		self::assertSame(['default', 'secondary'], $connectionRegistry->getNames());
		self::assertInstanceOf(BunnyManager::class, $connectionRegistry->get('default'));
		self::assertInstanceOf(BunnyManager::class, $connectionRegistry->get('secondary'));

		// Each named connection produces its own BunnyManager service.
		self::assertNotSame($connectionRegistry->get('default'), $connectionRegistry->get('secondary'));
	}


	public function testConnectionVhostsAreSeparated(): void
	{
		$container = ContainerFactory::create(__DIR__ . '/../config/multiConnection.neon');

		/** @var ConnectionRegistry $connectionRegistry */
		$connectionRegistry = $container->getByType(ConnectionRegistry::class);

		self::assertSame('/default', $connectionRegistry->get('default')->getConnection()['vhost']);
		self::assertSame('/secondary', $connectionRegistry->get('secondary')->getConnection()['vhost']);
	}


	public function testComponentsAreAssignedToTheirConnection(): void
	{
		$container = ContainerFactory::create(__DIR__ . '/../config/multiConnection.neon');

		/** @var ConnectionRegistry $connectionRegistry */
		$connectionRegistry = $container->getByType(ConnectionRegistry::class);

		// The secondary consumer is reachable only via its own connection.
		self::assertNotNull($connectionRegistry->get('secondary')->getConsumerByAlias('secondary'));
		self::assertNull($connectionRegistry->get('default')->getConsumerByAlias('secondary'));
	}

}
