<?php declare(strict_types=1);

namespace Portiny\Console\Tests\Adapter\Nette;

use Portiny\Console\Tests\AbstractContainerTestCase;
use Portiny\Console\Tests\Source\SomeBasicCommand;
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

		$this->assertCount(3, $application->all());
		$this->assertTrue($application->has('some-basic'));
		$this->assertInstanceOf(SomeBasicCommand::class, $application->get('some-basic'));
	}

	public function testExecution(): void
	{
		/** @var Application $application */
		$application = $this->container->getByType(Application::class);

		$command = $application->find('some-basic');
		$commandTester = new CommandTester($command);
		$commandTester->execute([
			'command' => $command->getName(),
		]);

		$output = $commandTester->getDisplay();
		$this->assertContains('Success!', $output);
	}
}
