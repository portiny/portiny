<?php

declare(strict_types = 1);

namespace Portiny\GraphQL\Contract\Provider;

use Portiny\GraphQL\Contract\Mutation\MutationFieldInterface;
use Portiny\GraphQL\Exception\Provider\ExistingMutationFieldException;


interface MutationFieldsProviderInterface
{

	/**
	 * @throws ExistingMutationFieldException
	 */
	function addField(MutationFieldInterface $mutationField);


	/**
	 * @return MutationFieldInterface[]
	 */
	function getFields(): array;


	function convertFieldsToArray(array $allowedMutations = NULL): array;

}
