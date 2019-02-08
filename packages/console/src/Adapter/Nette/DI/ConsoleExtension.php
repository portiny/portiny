<?php

declare(strict_types=1);

namespace Portiny\Console\Adapter\Nette\DI;

use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\Http\IRequest;
use Nette\Http\RequestFactory as NetteRequestFactory;
use Portiny\Console\Http\HttpRequestFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Helper\HelperSet;

class ConsoleExtension extends CompilerExtension
{
	/**
	 * @var array
	 */
	private $defaults = [
		'url' => null,
		'autoExit' => null,
		'catchExceptions' => null,
	];

	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->loadFromFile(__DIR__ . '/../config/config.neon');
		Compiler::loadDefinitions($builder, $config['services'] ?: []);

		$this->setupApplication();
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		$builder->getDefinitionByType(Application::class)
			->addSetup('setHelperSet', ['@' . $builder->getByType(HelperSet::class)]);

		$this->registerConsoleCommands();
		$this->registerHelpers();
	}

	private function setupApplication(): void
	{
		$config = $this->validateConfig($this->defaults);
		$builder = $this->getContainerBuilder();
		$applicationDefinition = $builder->getDefinitionByType(Application::class);

		if ($config['autoExit'] !== null) {
			$applicationDefinition->addSetup('setAutoExit', [(bool) $config['autoExit']]);
		}

		if ($config['catchExceptions'] !== null) {
			$applicationDefinition->addSetup('setCatchExceptions', [(bool) $config['catchExceptions']]);
		}

		if (interface_exists(IRequest::class) && $config['url']) {
			$builder->getDefinition($builder->getByType(NetteRequestFactory::class) ?: 'nette.httpRequestFactory')
				->setFactory(HttpRequestFactory::class)
				->addSetup('setRequestUrl', [$config['url']]);
		}
	}

	private function registerConsoleCommands(): void
	{
		$builder = $this->getContainerBuilder();
		$applicationDefinition = $builder->getDefinitionByType(Application::class);

		$commandDefinitions = $builder->findByType(Command::class);
		foreach (array_keys($commandDefinitions) as $name) {
			$applicationDefinition->addSetup('add', ['@' . $name]);
		}
	}

	private function registerHelpers(): void
	{
		$builder = $this->getContainerBuilder();
		$helperSetDefinition = $builder->getDefinitionByType(HelperSet::class);

		foreach ($builder->findByType(HelperInterface::class) as $helperDefinition) {
			$helperSetDefinition->addSetup('set', ['@' . $helperDefinition->getType(), null]);
		}
	}
}
