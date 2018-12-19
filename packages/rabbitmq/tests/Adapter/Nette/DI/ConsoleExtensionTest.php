<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Tests\Adapter\Nette\DI;

use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Tests\AbstractContainerTestCase;

final class ConsoleExtensionTest extends AbstractContainerTestCase
{
	public function testLoadConfiguration(): void
	{
		$bunnyManager = $this->container->getByType(BunnyManager::class);
		$this->assertInstanceOf(BunnyManager::class, $bunnyManager);
	}
}
