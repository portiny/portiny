<?php declare(strict_types = 1);

namespace Portiny\RabbitMQNette\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Command\ConsumeCommand;
use Portiny\RabbitMQ\Command\DeclareCommand;
use Portiny\RabbitMQ\ConnectionRegistry;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;
use Portiny\RabbitMQ\Exchange\AbstractExchange;
use Portiny\RabbitMQ\Producer\Producer;
use Portiny\RabbitMQ\Queue\AbstractQueue;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Application;
use Throwable;

final class RabbitMQExtension extends CompilerExtension
{

	private const DEFAULT_CONNECTION = 'default';


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'defaultConnection' => Expect::string(self::DEFAULT_CONNECTION),
			// New multi-connection configuration, keyed by connection name.
			'connections' => Expect::arrayOf($this->getConnectionSchema()),
			// BC: legacy flat "connection" configuration. When present (and no "connections" provided),
			// it is mapped to the "default" connection.
			'connection' => Expect::anyOf($this->getConnectionSchema(), Expect::null()),
			'aliases' => Expect::array(),
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

		/** @var stdClass $config */
		$config = $this->config;

		$connections = $this->resolveConnections($config);
		$components = $this->collectComponentsByConnection($builder, array_keys($connections));

		$managerReferences = [];
		foreach ($connections as $name => $connection) {
			$componentsForConnection = ($components[$name] ?? []) + [
				'consumers' => [],
				'exchanges' => [],
				'queues' => [],
			];

			$managerDefinition = $builder->addDefinition($this->prefix('manager.' . $name))
				->setFactory(BunnyManager::class, [
					$connection,
					$config->aliases,
					$componentsForConnection['consumers'],
					$componentsForConnection['exchanges'],
					$componentsForConnection['queues'],
				]);

			$managerReferences[$name] = '@' . $managerDefinition->getName();
		}

		$builder->addDefinition($this->prefix('connectionRegistry'))
			->setType(ConnectionRegistry::class)
			->setArguments([$managerReferences, $config->defaultConnection]);
	}


	private function getConnectionSchema(): Schema
	{
		$defaults = $this->getConnectionDefaults();

		return Expect::structure([
			'host' => Expect::string($defaults['host']),
			'port' => Expect::int($defaults['port'])->nullable(),
			'user' => Expect::string($defaults['user'])->nullable(),
			'password' => Expect::string($defaults['password'])->nullable(),
			'vhost' => Expect::string($defaults['vhost']),
			'timeout' => Expect::int($defaults['timeout']),
			'heartbeat' => Expect::float($defaults['heartbeat']),
			'persistent' => Expect::bool($defaults['persistent']),
			'path' => Expect::string($defaults['path']),
			'tcp_nodelay' => Expect::bool($defaults['tcp_nodelay']),
			'tcp_keepalive' => Expect::bool($defaults['tcp_keepalive']),
			'tcp_keepalive_idle' => Expect::int($defaults['tcp_keepalive_idle']),
			'tcp_keepalive_interval' => Expect::int($defaults['tcp_keepalive_interval']),
			'tcp_keepalive_count' => Expect::int($defaults['tcp_keepalive_count']),
		])->castTo('array');
	}


	/**
	 * @return array<string, mixed>
	 */
	private function getConnectionDefaults(): array
	{
		return [
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
			'tcp_keepalive' => false,
			'tcp_keepalive_idle' => 60,
			'tcp_keepalive_interval' => 30,
			'tcp_keepalive_count' => 4,
		];
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


	/**
	 * Resolves the final map of connections, applying backward compatibility for the legacy flat "connection" config.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function resolveConnections(stdClass $config): array
	{
		/** @var array<string, array<string, mixed>> $connections */
		$connections = $config->connections;

		// BC: when only the legacy "connection" is provided, map it to the "default" connection.
		if ($connections === [] && $config->connection !== null) {
			$connections[self::DEFAULT_CONNECTION] = (array) $config->connection;
		}

		// Ensure the default connection always exists with default values.
		if (! isset($connections[$config->defaultConnection])) {
			$connections[$config->defaultConnection] = $this->getConnectionDefaults();
		}

		return $connections;
	}


	/**
	 * Groups all consumer/exchange/queue services by the connection they belong to (read via getConnectionName()).
	 *
	 * @param array<int, string> $availableConnections
	 * @return array<string, array{
	 *     consumers: array<int, string>,
	 *     exchanges: array<int, string>,
	 *     queues: array<int, string>,
	 * }>
	 */
	private function collectComponentsByConnection(ContainerBuilder $containerBuilder, array $availableConnections): array
	{
		$components = [];

		$mapping = [
			'consumers' => AbstractConsumer::class,
			'exchanges' => AbstractExchange::class,
			'queues' => AbstractQueue::class,
		];

		foreach ($mapping as $group => $baseType) {
			foreach ($containerBuilder->findByType($baseType) as $serviceDefinition) {
				$className = $this->resolveDefinitionClass($serviceDefinition);
				if ($className === null) {
					continue;
				}

				$connectionName = $this->resolveConnectionName($className);
				$this->assertConnectionExists($connectionName, $className, $availableConnections);

				$components[$connectionName][$group][] = '@' . $serviceDefinition->getType();
			}
		}

		return $components;
	}


	private function resolveDefinitionClass(Definition $definition): ?string
	{
		$type = $definition->getType();
		if ($type !== null) {
			return $type;
		}

		// Fallback to the factory entity when the type is not explicitly set.
		if ($definition instanceof FactoryDefinition) {
			$entity = $definition->getResultDefinition()->getFactory()->getEntity();

			return is_string($entity) ? $entity : null;
		}

		if ($definition instanceof ServiceDefinition) {
			$factory = $definition->getFactory();
			if ($factory instanceof Statement && is_string($factory->getEntity())) {
				return $factory->getEntity();
			}
		}

		return null;
	}


	/**
	 * Reads the connection name from a component class via reflection, without invoking its constructor.
	 */
	private function resolveConnectionName(string $className): string
	{
		try {
			/** @var AbstractConsumer|AbstractExchange|AbstractQueue $instance */
			$instance = (new ReflectionClass($className))->newInstanceWithoutConstructor();

			return $instance->getConnectionName();
		} catch (Throwable $throwable) {
			return self::DEFAULT_CONNECTION;
		}
	}


	/**
	 * @param array<int, string> $availableConnections
	 */
	private function assertConnectionExists(
		string $connectionName,
		string $className,
		array $availableConnections
	): void {
		if (in_array($connectionName, $availableConnections, true)) {
			return;
		}

		throw new RuntimeException(sprintf(
			'RabbitMQ component "%s" requires connection "%s" which is not defined. '
			. 'Define it under "rabbitmq.connections.%s" or fix its getConnectionName(). '
			. 'Available connections: %s.',
			$className,
			$connectionName,
			$connectionName,
			$availableConnections === [] ? '(none)' : implode(', ', $availableConnections)
		));
	}


	private function hasSymfonyConsole(): bool
	{
		return class_exists(Application::class);
	}

}
