<?php

declare(strict_types=1);

namespace Portiny\GraphQL\Adapter\Nette\DI;

use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\Contract\Mutation\MutationFieldInterface;
use Portiny\GraphQL\Contract\Provider\MutationFieldsProviderInterface;
use Portiny\GraphQL\Contract\Provider\QueryFieldsProviderInterface;
use Portiny\GraphQL\GraphQL\RequestProcessor;
use Portiny\GraphQL\Provider\MutationFieldsProvider;
use Portiny\GraphQL\Provider\QueryFieldsProvider;

final class GraphQLExtension extends CompilerExtension
{
	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->loadFromFile(__DIR__ . '/../config/config.neon');
		Compiler::loadDefinitions($builder, $config['services'] ?: []);
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile(): void
	{
		$this->setupMutationFieldProvider();
		$this->setupQueryFieldProvider();
		$this->setupRequestProcessor();
	}

	private function setupMutationFieldProvider(): void
	{
		$containerBuilder = $this->getContainerBuilder();

		$mutationFieldProvider = $containerBuilder->addDefinition($this->prefix('mutationFieldsProvider'))
			->setFactory(MutationFieldsProvider::class)
			->setType(MutationFieldsProviderInterface::class)
			->setInject(FALSE);

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
			->setType(QueryFieldsProviderInterface::class)
			->setInject(FALSE);

		$queryFieldDefinitions = $containerBuilder->findByType(QueryFieldInterface::class);
		foreach ($queryFieldDefinitions as $queryFieldDefinition) {
			$queryFieldProvider->addSetup('addField', ['@' . $queryFieldDefinition->getType()]);
		}
	}

	private function setupRequestProcessor(): void
	{
		$containerBuilder = $this->getContainerBuilder();

		$containerBuilder->addDefinition($this->prefix('requestProcessor'))
			->setFactory(RequestProcessor::class)
			->addSetup('setMutationFieldsProvider', ['@' . MutationFieldsProviderInterface::class])
			->addSetup('setQueryFieldsProvider', ['@' . QueryFieldsProviderInterface::class]);
	}
}
