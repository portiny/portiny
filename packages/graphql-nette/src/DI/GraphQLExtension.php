<?php declare(strict_types = 1);

namespace Portiny\GraphQLNette\DI;

use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\Contract\Mutation\MutationFieldInterface;
use Portiny\GraphQL\Contract\Provider\MutationFieldsProviderInterface;
use Portiny\GraphQL\Contract\Provider\QueryFieldsProviderInterface;
use Portiny\GraphQL\GraphQL\RequestProcessor;
use Portiny\GraphQL\GraphQL\Schema\SchemaCacheProvider;
use Portiny\GraphQL\Provider\MutationFieldsProvider;
use Portiny\GraphQL\Provider\QueryFieldsProvider;
use stdClass;
use Tracy\IBarPanel;

final class GraphQLExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		$params = $this->getContainerBuilder()->parameters;
		$tempDir = $params['tempDir'] ?? '';

		return Expect::structure([
			'debug' => Expect::bool(interface_exists(IBarPanel::class)),
			'schemaCache' => Expect::structure([
				'enabled' => Expect::bool(false),
				'cacheDir' => Expect::string($tempDir . '/cache/graphql'),
			]),
		]);
	}


	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		/** @var stdClass $config */
		$config = (object) $this->config;
		$containerBuilder = $this->getContainerBuilder();

		$containerBuilder->addDefinition($this->prefix('requestProcessor'))
			->setFactory(RequestProcessor::class, [$config->debug])
			->addSetup('setSchemaCache', [$config->schemaCache->enabled]);

		$containerBuilder->addDefinition($this->prefix('schemaCacheProvider'))
			->setFactory(SchemaCacheProvider::class, [$config->schemaCache->cacheDir]);
	}


	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile(): void
	{
		$this->setupMutationFieldProvider();
		$this->setupQueryFieldProvider();
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

}
