<?php declare(strict_types = 1);

namespace Portiny\RabbitMQNette\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Command\ConsumeCommand;
use Portiny\RabbitMQ\Command\DeclareCommand;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;
use Portiny\RabbitMQ\Exchange\AbstractExchange;
use Portiny\RabbitMQ\Producer\Producer;
use Portiny\RabbitMQ\Queue\AbstractQueue;
use stdClass;
use Symfony\Component\Console\Application;

final class RabbitMQExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'aliases' => Expect::array(),
			'connection' => Expect::structure([
				'host' => Expect::string('127.0.0.1'),
				'port' => Expect::int(5672)->nullable(),
				'user' => Expect::string('guest')->nullable(),
				'password' => Expect::string('guest')->nullable(),
				'vhost' => Expect::string('/'),
				'timeout' => Expect::int(1),
				'heartbeat' => Expect::float(60.0),
				'persistent' => Expect::bool(false),
				'path' => Expect::string('/'),
				'tcp_nodelay' => Expect::bool(false),
			]),
		]);
	}


	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('producer'))
			->setType(Producer::class);

		// import RabbitMQ commands into Symfony/Console if exists
		$this->registerCommandsIntoConsole($builder);
	}


	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->createConfig($builder);

		$this->setupBunnyManager($builder, $config);
	}


	private function registerCommandsIntoConsole(ContainerBuilder $containerBuilder): void
	{
		if ($this->hasSymfonyConsole()) {
			$commands = [ConsumeCommand::class, DeclareCommand::class];
			foreach ($commands as $index => $command) {
				$containerBuilder->addDefinition($this->prefix('command.' . $index))
					->setType($command);
			}
		}
	}


	private function createConfig(ContainerBuilder $containerBuilder): stdClass
	{
		/** @var stdClass $config */
		$config = (object) $this->config;

		$config->consumers = [];
		foreach ($containerBuilder->findByType(AbstractConsumer::class) as $serviceDefinition) {
			$config->consumers[] = '@' . $serviceDefinition->getType();
		}

		$config->exchanges = [];
		foreach ($containerBuilder->findByType(AbstractExchange::class) as $serviceDefinition) {
			$config->exchanges[] = '@' . $serviceDefinition->getType();
		}

		$config->queues = [];
		foreach ($containerBuilder->findByType(AbstractQueue::class) as $serviceDefinition) {
			$config->queues[] = '@' . $serviceDefinition->getType();
		}

		return $config;
	}


	private function setupBunnyManager(ContainerBuilder $containerBuilder, stdClass $config): void
	{
		$containerBuilder->addDefinition($this->prefix('bunnyManager'))
			->setFactory(BunnyManager::class, [
				(array) $config->connection,
				$config->aliases,
				$config->consumers,
				$config->exchanges,
				$config->queues,
			]);
	}


	private function hasSymfonyConsole(): bool
	{
		return class_exists(Application::class);
	}

}
