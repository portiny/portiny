<?php

declare(strict_types = 1);

namespace Portiny\GraphQL\Contract\Field;

use GraphQL\Type\Definition\Type;

interface QueryFieldInterface
{

	/**
	 * @return string
	 */
	public function getName(): string;


	public function getType(): Type;


	public function getDescription(): string;


	/**
	 * @return array
	 */
	public function getArgs(): array;


	/**
	 * @param array $root
	 * @param array $args
	 * @param mixed|NULL $context
	 * @return mixed
	 */
	public function resolve(array $root, array $args, $context = NULL);
}
