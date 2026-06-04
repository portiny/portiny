<?php declare(strict_types = 1);

namespace Portiny\RabbitMQSymfony\DependencyInjection;

use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Command\ConsumeCommand;
use Portiny\RabbitMQ\Command\DeclareCommand;
use Portiny\RabbitMQ\ConnectionRegistry;
use Portiny\RabbitMQ\Producer\Producer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @phpstan-type ProcessedConfig array{
 *     default_connection: string,
 *     connections: array<string, array<string, mixed>>,
 *     aliases: array<string, string>
 * }
 */
final class PortinyRabbitMQSymfonyExtension extends Extension
{

	/**
	 * Configuration root key (also the extension alias used in the application config).
	 */
	public const ALIAS = 'portiny_rabbitmq';

	/**
	 * Prefix of the per-connection BunnyManager service ids.
	 */
	public const MANAGER_SERVICE_ID_PREFIX = 'portiny.rabbitmq.manager.';

	/**
	 * Service id of the connection registry.
	 */
	public const CONNECTION_REGISTRY_SERVICE_ID = 'portiny.rabbitmq.connection_registry';

	/**
	 * Container parameter holding the list of configured connection names (used by the compiler pass).
	 */
	public const CONNECTION_NAMES_PARAMETER = 'portiny.rabbitmq.connection_names';

	/**
	 * Legacy parameter holding a single connection definition (backward compatibility).
	 */
	private const LEGACY_CONNECTION_PARAMETER = 'portiny.rabbitmq.connection';

	/**
	 * Legacy parameter holding the consumer aliases map (backward compatibility).
	 */
	private const LEGACY_ALIASES_PARAMETER = 'portiny.rabbitmq.aliases';

	/**
	 * @param array<int, array<string, mixed>> $configs
	 */
	public function load(array $configs, ContainerBuilder $containerBuilder): void
	{
		$loader = new YamlFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../Resources/config'));
		$loader->load('services.yaml');

		$configuration = new Configuration();
		/** @var ProcessedConfig $config */
		$config = $this->processConfiguration($configuration, $configs);

		[$defaultConnection, $connections, $aliases] = $this->applyBackwardCompatibility($containerBuilder, $config);

		$this->registerConnections($containerBuilder, $connections, $aliases, $defaultConnection);
	}

	/**
	 * Returns the BunnyManager service id for a given connection name.
	 */
	public static function getManagerServiceId(string $connectionName): string
	{
		return self::MANAGER_SERVICE_ID_PREFIX . $connectionName;
	}

	public function getAlias(): string
	{
		return self::ALIAS;
	}

	/**
	 * Resolves the final connection map honouring legacy single-connection parameters.
	 *
	 * @param ProcessedConfig $config
	 * @return array{0: string, 1: array<string, array<string, mixed>|Parameter>, 2: array<string, string>|Parameter}
	 */
	private function applyBackwardCompatibility(ContainerBuilder $containerBuilder, array $config): array
	{
		$defaultConnection = $config['default_connection'];
		/** @var array<string, array<string, mixed>|Parameter> $connections */
		$connections = $config['connections'];
		/** @var array<string, string>|Parameter $aliases */
		$aliases = $config['aliases'];

		// No explicit connections configured: fall back to the legacy single-connection setup so existing
		// applications (e.g. conviu-core) keep working without any configuration change. The legacy
		// parameter is passed as a lazy reference (not its current value): reading it here would return the
		// bundle's services.yaml default, which shadows the application-provided value during load(). A
		// reference is resolved late, so the application value wins and env placeholders keep working.
		if ($connections === []) {
			$connections = [
				$defaultConnection => $containerBuilder->hasParameter(self::LEGACY_CONNECTION_PARAMETER)
					? new Parameter(self::LEGACY_CONNECTION_PARAMETER)
					: [],
			];
		}

		// Inherit legacy aliases when none are configured semantically (also as a lazy reference).
		if ($aliases === [] && $containerBuilder->hasParameter(self::LEGACY_ALIASES_PARAMETER)) {
			$aliases = new Parameter(self::LEGACY_ALIASES_PARAMETER);
		}

		return [$defaultConnection, $connections, $aliases];
	}

	/**
	 * @param array<string, array<string, mixed>|Parameter> $connections
	 * @param array<string, string>|Parameter $aliases
	 */
	private function registerConnections(
		ContainerBuilder $containerBuilder,
		array $connections,
		$aliases,
		string $defaultConnection
	): void {
		$managerReferences = [];

		foreach ($connections as $connectionName => $connection) {
			$managerId = self::getManagerServiceId($connectionName);

			$managerDefinition = new Definition(BunnyManager::class);
			$managerDefinition->setPublic(true);
			$managerDefinition->setArguments([
				$connection instanceof Parameter ? $connection : $this->normalizeConnection($connection),
				$aliases,
				// Consumers/exchanges/queues are filled per connection by ConnectionAwareCompilerPass.
				[],
				[],
				[],
			]);

			$containerBuilder->setDefinition($managerId, $managerDefinition);

			$managerReferences[$connectionName] = new Reference($managerId);
		}

		$containerBuilder->setParameter(self::CONNECTION_NAMES_PARAMETER, array_keys($connections));

		// Backward-compatible alias: legacy code injecting BunnyManager by FQCN resolves to the default
		// connection's manager.
		$defaultManagerId = self::getManagerServiceId($defaultConnection);
		if ($containerBuilder->hasDefinition($defaultManagerId)) {
			$containerBuilder->setAlias(BunnyManager::class, $defaultManagerId)->setPublic(true);
		}

		$this->registerConnectionRegistry($containerBuilder, $managerReferences, $defaultConnection);
	}

	/**
	 * Drops the ssl option when disabled so the connection array stays identical to the legacy format
	 * that is passed straight to the Bunny client.
	 *
	 * @param array<string, mixed> $connection
	 * @return array<string, mixed>
	 */
	private function normalizeConnection(array $connection): array
	{
		if (array_key_exists('ssl', $connection) && $connection['ssl'] === false) {
			unset($connection['ssl']);
		}

		return $connection;
	}

	/**
	 * @param array<string, Reference> $managerReferences
	 */
	private function registerConnectionRegistry(
		ContainerBuilder $containerBuilder,
		array $managerReferences,
		string $defaultConnection
	): void {
		$registryDefinition = new Definition(ConnectionRegistry::class);
		$registryDefinition->setPublic(true);
		$registryDefinition->setArguments([$managerReferences, $defaultConnection]);

		$containerBuilder->setDefinition(self::CONNECTION_REGISTRY_SERVICE_ID, $registryDefinition);

		// Autowiring alias so Producer/ConsumeCommand/DeclareCommand receive the registry by FQCN.
		$containerBuilder->setAlias(ConnectionRegistry::class, self::CONNECTION_REGISTRY_SERVICE_ID);

		$this->registerCoreServices($containerBuilder);
	}

	private function registerCoreServices(ContainerBuilder $containerBuilder): void
	{
		$registryReference = new Reference(self::CONNECTION_REGISTRY_SERVICE_ID);

		$producerDefinition = new Definition(Producer::class);
		$producerDefinition->setPublic(true);
		$producerDefinition->setArgument(0, $registryReference);
		$containerBuilder->setDefinition(Producer::class, $producerDefinition);

		$consumeCommandDefinition = new Definition(ConsumeCommand::class);
		$consumeCommandDefinition->setArgument(0, $registryReference);
		$consumeCommandDefinition->addTag('console.command');
		$containerBuilder->setDefinition(ConsumeCommand::class, $consumeCommandDefinition);

		$declareCommandDefinition = new Definition(DeclareCommand::class);
		$declareCommandDefinition->setArgument(0, $registryReference);
		$declareCommandDefinition->addTag('console.command');
		$containerBuilder->setDefinition(DeclareCommand::class, $declareCommandDefinition);
	}

}
