<?php

declare(strict_types=1);

namespace Portiny\GraphQL\Adapter\Nette\DI;

use Nette\DI\CompilerExtension;
use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\Contract\Http\Request\RequestParserInterface;
use Portiny\GraphQL\Contract\Mutation\MutationFieldInterface;
use Portiny\GraphQL\Contract\Provider\MutationFieldsProviderInterface;
use Portiny\GraphQL\Contract\Provider\QueryFieldsProviderInterface;
use Portiny\GraphQL\GraphQL\RequestProcessor;
use Portiny\GraphQL\GraphQL\Schema\SchemaCacheProvider;
use Portiny\GraphQL\Http\Request\JsonRequestParser;
use Portiny\GraphQL\Provider\MutationFieldsProvider;
use Portiny\GraphQL\Provider\QueryFieldsProvider;

final class GraphQLExtension extends CompilerExtension
{
	/**
	 * @var array
	 */
	private static $defaults = [
		'schemaCache' => [
			'enabled' => false,
			'cacheDir' => '%tempDir%/cache/graphql',
		],
	];

	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		$config = $this->getConfig(self::$defaults);
		$containerBuilder = $this->getContainerBuilder();

		$containerBuilder->addDefinition($this->prefix('requestProcessor'))
			->setFactory(RequestProcessor::class)
			->addSetup('setSchemaCache', [$config['schemaCache']['enabled']]);

		$containerBuilder->addDefinition($this->prefix('schemaCacheProvider'))
			->setFactory(SchemaCacheProvider::class, [$config['schemaCache']['cacheDir']]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile(): void
	{
		$this->setupMutationFieldProvider();
		$this->setupQueryFieldProvider();
		$this->setupRequestParser();
	}

	private function setupMutationFieldProvider(): void
	{
		$containerBuilder = $this->getContainerBuilder();

		$mutationFieldProvider = $containerBuilder->addDefinition($this->prefix('mutationFieldsProvider'))
			->setFactory(MutationFieldsProvider::class)
			->setType(MutationFieldsProviderInterface::class);

		$mutationFieldDefinitions = $containerBuilder->findByType(MutationFieldInterface::class);
		foreach ($mutationFieldDefinitions as $mutationFieldDefinition) {
			$mutationFieldProvider->addSetup('addField', ['@' . $mutationFieldDefinition->getType()]);
		}
	}

	private function setupQueryFieldProvider(): void
	{
		$containerBuilder = $this->getContainerBuilder();

		$queryFieldProvider = $containerBuilder->addDefinition($this->prefix('queryFieldsProvider'))
			->setFactory(QueryFieldsProvider::class)
			->setType(QueryFieldsProviderInterface::class);

		$queryFieldDefinitions = $containerBuilder->findByType(QueryFieldInterface::class);
		foreach ($queryFieldDefinitions as $queryFieldDefinition) {
			$queryFieldProvider->addSetup('addField', ['@' . $queryFieldDefinition->getType()]);
		}
	}

	private function setupRequestParser(): void
	{
		$containerBuilder = $this->getContainerBuilder();

		if (! $containerBuilder->findByType(RequestParserInterface::class)) {
			$containerBuilder->addDefinition($this->prefix('jsonRequestParser'))
				->setFactory(JsonRequestParser::class);
		}
	}
}
