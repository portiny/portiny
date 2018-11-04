<?php

declare(strict_types=1);

namespace Portiny\GraphQL\GraphQL;

use GraphQL\Error\Debug;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Portiny\GraphQL\Contract\Http\Request\RequestParserInterface;
use Portiny\GraphQL\Contract\Provider\MutationFieldsProviderInterface;
use Portiny\GraphQL\Contract\Provider\QueryFieldsProviderInterface;
use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;

final class RequestProcessor
{
	/**
	 * @var MutationFieldsProviderInterface
	 */
	private $mutationFieldsProvider;

	/**
	 * @var QueryFieldsProviderInterface
	 */
	private $queryFieldsProvider;

	public function setMutationFieldsProvider(MutationFieldsProviderInterface $mutationFieldsProvider): void
	{
		$this->mutationFieldsProvider = $mutationFieldsProvider;
	}

	public function setQueryFieldsProvider(QueryFieldsProviderInterface $queryFieldsProvider): void
	{
		$this->queryFieldsProvider = $queryFieldsProvider;
	}

	/**
	 * @param mixed|null $context
	 * @param array|null $allowedQueries
	 * @param array|null $allowedMutations
	 */
	public function process(
		RequestParserInterface $requestParser,
		array $rootValue = [],
		$context = NULL,
		?array $allowedQueries = NULL,
		?array $allowedMutations = NULL,
		?ILogger $logger = NULL
	): array {
		try {
			$result = GraphQL::executeQuery(
				$this->createSchema($allowedQueries, $allowedMutations),
				$requestParser->getQuery(),
				$rootValue,
				$context,
				$requestParser->getVariables()
			);

			$output = $result->toArray($this->isDebug());
		} catch (Throwable $exception) {
			if ($logger) {
				$logger->log($exception);
			}

			$output = [
				'error' => [
					'message' => $exception->getMessage(),
				],
			];
		}

		return $output;
	}

	/**
	 * @param mixed|null $context
	 * @param array|null $allowedQueries
	 * @param array|null $allowedMutations
	 */
	public function processViaPromise(
		PromiseAdapter $promiseAdapter,
		RequestParserInterface $requestParser,
		array $rootValue = [],
		$context = NULL,
		?array $allowedQueries = NULL,
		?array $allowedMutations = NULL,
		?ILogger $logger = NULL
	): Promise {
		try {
			return GraphQL::promiseToExecute(
				$promiseAdapter,
				$this->createSchema($allowedQueries, $allowedMutations),
				$requestParser->getQuery(),
				$rootValue,
				$context,
				$requestParser->getVariables()
			);
		} catch (Throwable $exception) {
			if ($logger) {
				$logger->log($exception);
			}

			return $promiseAdapter->createRejected($exception);
		}
	}

	private function createSchema(?array $allowedQueries = NULL, ?array $allowedMutations = NULL): Schema
	{
		$configuration = [
			'query' => $this->createQueryObject($allowedQueries),
		];

		$mutationObject = $this->createMutationObject($allowedMutations);
		if ($mutationObject->getFields()) {
			$configuration['mutation'] = $mutationObject;
		}

		return new Schema($configuration);
	}

	private function createQueryObject(?array $allowedQueries = NULL): ObjectType
	{
		return new ObjectType([
			'name' => 'Query',
			'fields' => $this->queryFieldsProvider->convertFieldsToArray($allowedQueries),
		]);
	}

	private function createMutationObject(?array $allowedMutations = NULL): ObjectType
	{
		return new ObjectType([
			'name' => 'Mutation',
			'fields' => $this->mutationFieldsProvider->convertFieldsToArray($allowedMutations),
		]);
	}

	private function isDebug(): int
	{
		return ! Debugger::$productionMode ? Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE : 0;
	}
}
