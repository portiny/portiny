<?php declare(strict_types = 1);

namespace Portiny\RabbitMQSymfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{

	/**
	 * Name of the default connection used when a component does not override getConnectionName().
	 */
	public const DEFAULT_CONNECTION_NAME = 'default';

	/**
	 * {@inheritdoc}
	 */
	public function getConfigTreeBuilder(): TreeBuilder
	{
		$treeBuilder = new TreeBuilder('portiny_rabbitmq');

		// Compatibility across symfony/config 4.4 - 8.x (getRootNode() exists since 4.2).
		$rootNode = method_exists($treeBuilder, 'getRootNode')
			? $treeBuilder->getRootNode()
			: $treeBuilder->root('portiny_rabbitmq'); // @phpstan-ignore-line

		/** @var ArrayNodeDefinition $rootNode */
		$rootNode
			->children()
				->scalarNode('default_connection')
					->defaultValue(self::DEFAULT_CONNECTION_NAME)
					->cannotBeEmpty()
					->info('Name of the default connection used by components without an explicit connection.')
				->end()
				->append($this->createConnectionsNode())
				->variableNode('aliases')
					->defaultValue([])
					->info('Global map of consumer aliases: alias => consumer FQCN.')
				->end()
			->end();

		return $treeBuilder;
	}

	private function createConnectionsNode(): ArrayNodeDefinition
	{
		$treeBuilder = new TreeBuilder('connections');

		$node = method_exists($treeBuilder, 'getRootNode')
			? $treeBuilder->getRootNode()
			: $treeBuilder->root('connections'); // @phpstan-ignore-line

		/** @var ArrayNodeDefinition $node */
		$node
			->info('Map of named RabbitMQ connections keyed by connection name.')
			->useAttributeAsKey('name')
			->arrayPrototype()
				->children()
					->scalarNode('host')->defaultValue('127.0.0.1')->end()
					->integerNode('port')->defaultValue(5672)->end()
					->scalarNode('user')->defaultValue('guest')->end()
					->scalarNode('password')->defaultValue('guest')->end()
					->scalarNode('vhost')->defaultValue('/')->end()
					->scalarNode('timeout')->defaultValue(1)->end()
					->floatNode('heartbeat')->defaultValue(60.0)->end()
					->booleanNode('persistent')->defaultValue(false)->end()
					->scalarNode('path')->defaultValue('/')->end()
					->booleanNode('tcp_nodelay')->defaultValue(false)->end()
					// Kernel TCP keepalive: keeps long-lived/idle connections alive through an L4
					// load balancer that would otherwise drop them on its TCP idle timeout.
					->booleanNode('tcp_keepalive')->defaultValue(false)
						->info('Enable SO_KEEPALIVE on the connection socket.')
					->end()
					->integerNode('tcp_keepalive_idle')->defaultValue(60)
						->info('Seconds of idle before the first keepalive probe (TCP_KEEPIDLE).')
					->end()
					->integerNode('tcp_keepalive_interval')->defaultValue(30)
						->info('Seconds between keepalive probes (TCP_KEEPINTVL).')
					->end()
					->integerNode('tcp_keepalive_count')->defaultValue(4)
						->info('Unacknowledged probes before the connection is dropped (TCP_KEEPCNT).')
					->end()
					// ssl accepts either a bool (enable/disable) or an array of TLS context options.
					->variableNode('ssl')->defaultValue(false)->end()
				->end()
			->end();

		return $node;
	}

}
