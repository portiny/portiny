<?php

declare(strict_types = 1);

namespace Portiny\GraphQL\GraphQL;

use Exception;
use GraphQL\Error\Debug;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Tracy\Debugger;
use Portiny\GraphQL\Contract\Http\Request\RequestParserInterface;
use Portiny\GraphQL\Contract\Provider\MutationFieldsProviderInterface;
use Portiny\GraphQL\Contract\Provider\QueryFieldsProviderInterface;


final class RequestProcessor
{

	/**
	 * @var RequestParserInterface
	 */
	private $requestParser;

	/**
	 * @var MutationFieldsProviderInterface
	 */
	private $mutationFieldsProvider;

	/**
	 * @var QueryFieldsProviderInterface
	 */
	private $queryFieldsProvider;


	public function __construct(RequestParserInterface $requestParser)
	{
		$this->requestParser = $requestParser;
	}


	public function setMutationFieldsProvider(MutationFieldsProviderInterface $mutationFieldsProvider)
	{
		$this->mutationFieldsProvider = $mutationFieldsProvider;
	}


	public function setQueryFieldsProvider(QueryFieldsProviderInterface $queryFieldsProvider)
	{
		$this->queryFieldsProvider = $queryFieldsProvider;
	}


	/**
	 * @param array $rootValue
	 * @param mixed|NULL $context
	 * @param array|null $allowedQueries
	 * @param array|null $allowedMutations
	 * @return array
	 */
	public function process(
		array $rootValue = [],
		$context = NULL,
		array $allowedQueries = NULL,
		array $allowedMutations = NULL
	): array {
		try {
			$result = GraphQL::executeQuery(
				$this->createSchema($allowedQueries, $allowedMutations),
				$this->requestParser->getQuery(),
				$rootValue,
				$context,
				$this->requestParser->getVariables()
			);

			$output = $result->toArray($this->isDebug());

		} catch (Exception $exception) {
			$output = [
				'error' => [
					'message' => $exception->getMessage()
				]
			];
		}

		return $output;
	}


	private function createSchema(array $allowedQueries = NULL, array $allowedMutations = NULL): Schema
	{
		return new Schema([
			'query' => $this->createQueryObject($allowedQueries),
			'mutation' => $this->createMutationObject($allowedMutations),
		]);
	}


	private function createQueryObject(array $allowedQueries = NULL): ObjectType
	{
		return new ObjectType([
			'name' => 'Query',
			'fields' => $this->queryFieldsProvider->convertFieldsToArray($allowedQueries)
		]);
	}


	private function createMutationObject(array $allowedMutations = NULL): ObjectType
	{
		return new ObjectType([
			'name' => 'Mutation',
			'fields' => $this->mutationFieldsProvider->convertFieldsToArray($allowedMutations)
		]);
	}


	private function isDebug(): int
	{
		return ! Debugger::$productionMode ? Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE : 0;
	}

}
