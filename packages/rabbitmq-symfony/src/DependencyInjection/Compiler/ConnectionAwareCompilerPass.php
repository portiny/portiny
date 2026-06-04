<?php declare(strict_types = 1);

namespace Portiny\RabbitMQSymfony\DependencyInjection\Compiler;

use Portiny\RabbitMQSymfony\DependencyInjection\PortinyRabbitMQSymfonyExtension;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Throwable;

/**
 * Splits tagged consumers, exchanges and queues per RabbitMQ connection and rewires every
 * per-connection BunnyManager definition with only the components that belong to it.
 *
 * The connection a component belongs to is resolved (in order of precedence):
 *   1. the "connection" attribute of the tag (explicit override),
 *   2. the value returned by the component's getConnectionName() method (read via reflection),
 *   3. the "default" connection name as a last resort.
 *
 * Components pointing to a connection that is not defined cause a compile-time exception so the
 * misconfiguration is caught early instead of failing at runtime.
 */
final class ConnectionAwareCompilerPass implements CompilerPassInterface
{

	private const CONSUMER_TAG = 'portiny.rabbitmq.consumer';

	private const EXCHANGE_TAG = 'portiny.rabbitmq.exchange';

	private const QUEUE_TAG = 'portiny.rabbitmq.queue';

	private const DEFAULT_CONNECTION_NAME = 'default';

	public function process(ContainerBuilder $containerBuilder): void
	{
		$connectionNames = $this->getConnectionNames($containerBuilder);
		if ($connectionNames === []) {
			return;
		}

		$consumers = $this->groupServicesByConnection($containerBuilder, self::CONSUMER_TAG, $connectionNames);
		$exchanges = $this->groupServicesByConnection($containerBuilder, self::EXCHANGE_TAG, $connectionNames);
		$queues = $this->groupServicesByConnection($containerBuilder, self::QUEUE_TAG, $connectionNames);

		foreach ($connectionNames as $connectionName) {
			$managerId = PortinyRabbitMQSymfonyExtension::getManagerServiceId($connectionName);
			if (! $containerBuilder->hasDefinition($managerId)) {
				continue;
			}

			$definition = $containerBuilder->getDefinition($managerId);
			// Wrapped in IteratorArgument so the components stay lazy (mirrors the original
			// !tagged_iterator behaviour) and do not introduce circular references between the
			// manager and consumers that inject the Producer.
			$definition->setArgument(2, new IteratorArgument($consumers[$connectionName] ?? []));
			$definition->setArgument(3, new IteratorArgument($exchanges[$connectionName] ?? []));
			$definition->setArgument(4, new IteratorArgument($queues[$connectionName] ?? []));
		}
	}

	/**
	 * @return array<int, string>
	 */
	private function getConnectionNames(ContainerBuilder $containerBuilder): array
	{
		if (! $containerBuilder->hasParameter(PortinyRabbitMQSymfonyExtension::CONNECTION_NAMES_PARAMETER)) {
			return [];
		}

		/** @var array<int, string> $names */
		$names = $containerBuilder->getParameter(PortinyRabbitMQSymfonyExtension::CONNECTION_NAMES_PARAMETER);

		return $names;
	}

	/**
	 * @param array<int, string> $connectionNames
	 * @return array<string, array<int, Reference>>
	 */
	private function groupServicesByConnection(
		ContainerBuilder $containerBuilder,
		string $tag,
		array $connectionNames
	): array {
		$grouped = [];

		foreach ($containerBuilder->findTaggedServiceIds($tag) as $serviceId => $tags) {
			$definition = $containerBuilder->getDefinition($serviceId);
			$connectionName = $this->resolveConnectionName($containerBuilder, $serviceId, $definition, $tags);

			if (! in_array($connectionName, $connectionNames, true)) {
				throw new LogicException(sprintf(
					'RabbitMQ component "%s" (tag "%s") references connection "%s", which is not defined. '
						. 'Available connections: %s. Define it under '
						. 'portiny_rabbitmq.connections or fix getConnectionName().',
					$serviceId,
					$tag,
					$connectionName,
					implode(', ', $connectionNames)
				));
			}

			$grouped[$connectionName][] = new Reference($serviceId);
		}

		return $grouped;
	}

	/**
	 * @param array<int, array<string, mixed>> $tags
	 */
	private function resolveConnectionName(
		ContainerBuilder $containerBuilder,
		string $serviceId,
		Definition $definition,
		array $tags
	): string {
		// 1. Explicit override via tag attribute: tags: [{ name: ..., connection: foo }].
		foreach ($tags as $attributes) {
			if (isset($attributes['connection']) && $attributes['connection'] !== '') {
				return (string) $attributes['connection'];
			}
		}

		// 2. Read from the component class via reflection (single source of truth in the core).
		$class = $definition->getClass() ?? $serviceId;
		$class = $containerBuilder->getParameterBag()->resolveValue($class);

		if (is_string($class) && class_exists($class)) {
			$connectionName = $this->readConnectionNameFromClass($class);
			if ($connectionName !== null) {
				return $connectionName;
			}
		}

		// 3. Fallback to the default connection.
		return self::DEFAULT_CONNECTION_NAME;
	}

	/**
	 * @param class-string $class
	 */
	private function readConnectionNameFromClass(string $class): ?string
	{
		try {
			$reflectionClass = new ReflectionClass($class);
			if (! $reflectionClass->hasMethod('getConnectionName')) {
				return null;
			}

			$instance = $reflectionClass->newInstanceWithoutConstructor();

			/** @var string $connectionName */
			$connectionName = $instance->getConnectionName();

			return $connectionName !== '' ? $connectionName : null;
		} catch (Throwable $throwable) {
			// Class cannot be instantiated without a constructor or the method failed: use the fallback.
			return null;
		}
	}

}
