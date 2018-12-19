<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Tests\Adapter\Nette\DI;

use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Command\ConsumeCommand;
use Portiny\RabbitMQ\Command\DeclareCommand;
use Portiny\RabbitMQ\Tests\AbstractContainerTestCase;

final class ConsoleExtensionTest extends AbstractContainerTestCase
{
	public function testLoadConfiguration(): void
	{
		$bunnyManager = $this->container->getByType(BunnyManager::class);
		$this->assertInstanceOf(BunnyManager::class, $bunnyManager);

		$consumeCommand = $this->container->getByType(ConsumeCommand::class);
		$this->assertInstanceOf(ConsumeCommand::class, $consumeCommand);

		$declareCommand = $this->container->getByType(DeclareCommand::class);
		$this->assertInstanceOf(DeclareCommand::class, $declareCommand);
	}
}
