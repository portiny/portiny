<?php

declare(strict_types=1);

namespace Portiny\GraphQL\Contract\Mutation;

use GraphQL\Type\Definition\Type;

interface MutationFieldInterface
{
	public function getName(): string;

	public function getType(): Type;

	public function getDescription(): string;

	public function getArgs(): array;

	/**
	 * @param mixed|null $context
	 * @return mixed
	 */
	public function resolve(array $root, array $args, $context = NULL);
}
