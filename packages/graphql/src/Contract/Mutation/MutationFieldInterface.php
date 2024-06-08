<?php declare(strict_types = 1);

namespace Portiny\GraphQL\Contract\Mutation;

use GraphQL\Type\Definition\Type;

interface MutationFieldInterface
{

	public function getName(): string;


	public function getDescription(): string;


	/**
	 * @return mixed[]
	 */
	public function getArgs(): array;


	public function getType(): Type;


	/**
	 * @param mixed[] $root
	 * @param mixed[] $args
	 * @param mixed|null $context
	 * @return mixed
	 */
	public function resolve(array $root, array $args, $context = null);

}
