<?php

declare(strict_types=1);

namespace Portiny\Console\Adapter\Nette\DI;

use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\Statement;
use Nette\Http\Request;
use Nette\Http\UrlScript;
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
		'url' => NULL,
		'catchExceptions' => NULL,
		'autoExit' => NULL,
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

		if ($config['autoExit'] !== NULL) {
			$applicationDefinition->addSetup('setAutoExit', [(bool) $config['autoExit']]);
		}

		if ($config['catchExceptions'] !== NULL) {
			$applicationDefinition->addSetup('setCatchExceptions', [(bool) $config['catchExceptions']]);
		}

		if ($builder->hasDefinition('http.request') && $config['url'] !== NULL) {
			$builder->getDefinition('http.request')
				->setType(Request::class)
				->setArguments([
					new Statement(UrlScript::class, [$config['url']]),
				]);
		}
	}

	private function registerConsoleCommands(): void
	{
		$builder = $this->getContainerBuilder();
		$applicationDefinition = $builder->getDefinitionByType(Application::class);

		foreach ($builder->findByType(Command::class) as $name => $commandDefinition) {
			$applicationDefinition->addSetup('add', ['@' . $name]);
		}
	}

	private function registerHelpers(): void
	{
		$builder = $this->getContainerBuilder();
		$helperSetDefinition = $builder->getDefinitionByType(HelperSet::class);

		foreach ($builder->findByType(HelperInterface::class) as $helperDefinition) {
			$helperSetDefinition->addSetup(
				'set',
				['@' . $helperDefinition->getType(), NULL]
			);
		}
	}
}
