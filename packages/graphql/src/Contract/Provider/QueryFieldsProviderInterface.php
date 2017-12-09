<?php

declare(strict_types = 1);

namespace Portiny\GraphQL\Contract\Provider;

use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\Exception\Provider\ExistingQueryFieldException;


interface QueryFieldsProviderInterface
{

	/**
	 * @throws ExistingQueryFieldException
	 */
	function addField(QueryFieldInterface $queryField);


	/**
	 * @return QueryFieldInterface[]
	 */
	function getFields(): array;


	function convertFieldsToArray(array $allowedQueries = NULL): array;

}
