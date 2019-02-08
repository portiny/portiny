<?php

declare(strict_types=1);

namespace Portiny\GraphQL\Contract\Provider;

use Portiny\GraphQL\Contract\Mutation\MutationFieldInterface;
use Portiny\GraphQL\Exception\Provider\ExistingMutationFieldException;

interface MutationFieldsProviderInterface
{
	/**
	 * @throws ExistingMutationFieldException
	 */
	public function addField(MutationFieldInterface $mutationField): void;

	/**
	 * @return MutationFieldInterface[]
	 */
	public function getFields(): array;

	public function convertFieldsToArray(?array $allowedMutations = null): array;
}
