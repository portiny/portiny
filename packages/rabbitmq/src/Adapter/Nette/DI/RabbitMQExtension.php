<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Adapter\Nette\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Command\ConsumeCommand;
use Portiny\RabbitMQ\Command\DeclareCommand;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;
use Portiny\RabbitMQ\Exchange\AbstractExchange;
use Portiny\RabbitMQ\Producer\AbstractProducer;
use Portiny\RabbitMQ\Producer\Producer;
use Portiny\RabbitMQ\Queue\AbstractQueue;
use Symfony\Component\Console\Application;

final class RabbitMQExtension extends CompilerExtension
{
	/**
	 * @var array
	 */
	private $defaults = [
		'aliases' => [],
		'connection' => [
			'host' => '127.0.0.1',
			'port' => 5672,
			'user' => 'guest',
			'password' => 'guest',
			'vhost' => '/',
			'timeout' => 1,
			'heartbeat' => 60.0,
			'persistent' => false,
			'path' => '/',
			'tcp_nodelay' => false,
		],
	];

	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->createConfig($builder);

		$this->setupBunnyManager($builder, $config);

		// import RabbitMQ commands into Symfony/Console if exists
		$this->registerCommandsIntoConsole($builder);
	}

	private function createConfig(ContainerBuilder $containerBuilder): array
	{
		$config = $this->validateConfig($this->defaults);

		$config['consumers'] = [];
		foreach ($containerBuilder->findByType(AbstractConsumer::class) as $serviceDefinition) {
			$config['consumers'][] = $serviceDefinition->getType();
		}

		$config['exchanges'] = [];
		foreach ($containerBuilder->findByType(AbstractExchange::class) as $serviceDefinition) {
			$config['exchanges'][] = $serviceDefinition->getType();
		}

		$config['publishers'] = [];
		foreach ($containerBuilder->findByType(AbstractProducer::class) as $serviceDefinition) {
			$config['publishers'][] = $serviceDefinition->getType();
		}

		$config['queues'] = [];
		foreach ($containerBuilder->findByType(AbstractQueue::class) as $serviceDefinition) {
			$config['queues'][] = $serviceDefinition->getType();
		}

		return $config;
	}

	private function setupBunnyManager(ContainerBuilder $containerBuilder, array $config): void
	{
		$containerBuilder->addDefinition($this->prefix('bunnyManager'))
			->setFactory(BunnyManager::class, ['@container', $config]);

		$containerBuilder->addDefinition($this->prefix('producer'))
			->setType(Producer::class);
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

	private function hasSymfonyConsole(): bool
	{
		return class_exists(Application::class);
	}
}
