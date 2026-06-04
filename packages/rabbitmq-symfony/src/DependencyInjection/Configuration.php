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
					// ssl accepts either a bool (enable/disable) or an array of TLS context options.
					->variableNode('ssl')->defaultValue(false)->end()
				->end()
			->end();

		return $node;
	}

}
