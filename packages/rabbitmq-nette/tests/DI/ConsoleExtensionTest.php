<?php declare(strict_types = 1);

namespace Portiny\RabbitMQNette\Tests\DI;

use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQNette\Tests\AbstractContainerTestCase;

final class ConsoleExtensionTest extends AbstractContainerTestCase
{

	public function testLoadConfiguration(): void
	{
		$bunnyManager = $this->container->getByType(BunnyManager::class);
		self::assertInstanceOf(BunnyManager::class, $bunnyManager);
	}

}
