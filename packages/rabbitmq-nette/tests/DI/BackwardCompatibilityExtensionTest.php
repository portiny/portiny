<?php declare(strict_types = 1);

namespace Portiny\RabbitMQNette\Tests\DI;

use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\ConnectionRegistry;
use Portiny\RabbitMQNette\Tests\ContainerFactory;

final class BackwardCompatibilityExtensionTest extends TestCase
{

	public function testLegacyFlatConnectionMapsToDefault(): void
	{
		$container = ContainerFactory::create(__DIR__ . '/../config/legacyConnection.neon');

		/** @var ConnectionRegistry $connectionRegistry */
		$connectionRegistry = $container->getByType(ConnectionRegistry::class);

		self::assertSame(['default'], $connectionRegistry->getNames());

		$manager = $connectionRegistry->get('default');
		self::assertInstanceOf(BunnyManager::class, $manager);
		self::assertSame('rabbit.example.com', $manager->getConnection()['host']);
		self::assertSame('/legacy', $manager->getConnection()['vhost']);
	}

}
