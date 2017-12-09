<?php

declare(strict_types = 1);

namespace Portiny\GraphQL\Contract\Mutation;


use GraphQL\Type\Definition\Type;


interface MutationFieldInterface
{

	function getName(): string;


	function getType(): Type;


	function getDescription(): string;


	function getArgs(): array;


	/**
	 * @param array $root
	 * @param array $args
	 * @param mixed|NULL $context
	 * @return mixed
	 */
	function resolve(array $root, array $args, $context = NULL);

}
