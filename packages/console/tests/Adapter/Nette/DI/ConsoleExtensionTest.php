<?php declare(strict_types=1);

namespace Portiny\Console\Tests\Adapter\Nette\DI;

use Portiny\Console\Tests\AbstractContainerTestCase;
use Portiny\Console\Tests\Source\PrintRequestUrlCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleExtensionTest extends AbstractContainerTestCase
{
	public function testLoadConfiguration(): void
	{
		$application = $this->container->getByType(Application::class);
		$this->assertInstanceOf(Application::class, $application);
	}

	public function testBeforeCompile(): void
	{
		/** @var Application $application */
		$application = $this->container->getByType(Application::class);

		$this->assertFalse($application->isAutoExitEnabled());
		$this->assertTrue($application->areExceptionsCaught());
		$this->assertCount(3, $application->all());
		$this->assertTrue($application->has('print-request-url'));
		$this->assertInstanceOf(PrintRequestUrlCommand::class, $application->get('print-request-url'));
	}

	public function testExecution(): void
	{
		/** @var Application $application */
		$application = $this->container->getByType(Application::class);

		$command = $application->find('print-request-url');
		$commandTester = new CommandTester($command);
		$commandTester->execute([
			'command' => $command->getName(),
		]);

		$output = $commandTester->getDisplay();
		$this->assertSame('https://portiny.org/', $output);
	}
}
