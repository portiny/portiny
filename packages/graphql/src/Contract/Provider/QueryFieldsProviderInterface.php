<?php

declare(strict_types=1);

namespace Portiny\GraphQL\Contract\Provider;

use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\Exception\Provider\ExistingQueryFieldException;

interface QueryFieldsProviderInterface
{
	/**
	 * @throws ExistingQueryFieldException
	 */
	public function addField(QueryFieldInterface $queryField): void;

	/**
	 * @return QueryFieldInterface[]
	 */
	public function getFields(): array;

	public function convertFieldsToArray(?array $allowedQueries = null): array;
}
