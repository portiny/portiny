<?php declare(strict_types = 1);

namespace Portiny\Console\Adapter\Nette\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\Statement;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use stdClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Helper\HelperSet;

class ConsoleExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'url' => Expect::anyOf(Expect::string(), Expect::null()),
			'autoExit' => Expect::bool(),
			'catchExceptions' => Expect::bool(),
		]);
	}


	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		$config = $this->loadFromFile(__DIR__ . '/../config/config.neon');
		$this->loadDefinitionsFromConfig($config['services'] ?: []);

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
		/** @var stdClass $config */
		$config = (object) $this->config;
		$builder = $this->getContainerBuilder();
		$applicationDefinition = $builder->getDefinitionByType(Application::class);

		if ($config->autoExit !== null) {
			$applicationDefinition->addSetup('setAutoExit', [(bool) $config->autoExit]);
		}

		if ($config->catchExceptions !== null) {
			$applicationDefinition->addSetup('setCatchExceptions', [(bool) $config->catchExceptions]);
		}

		if ($config->url !== null && $builder->hasDefinition('http.request')) {
			$definition = $builder->getDefinition('http.request');
			$definition->setFactory(Request::class, [new Statement(UrlScript::class, [$config->url])]);
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
