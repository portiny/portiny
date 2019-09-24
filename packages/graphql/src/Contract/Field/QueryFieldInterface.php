<?php declare(strict_types = 1);

namespace Portiny\GraphQL\Contract\Field;

use GraphQL\Type\Definition\Type;

interface QueryFieldInterface
{

	public function getName(): string;


	public function getDescription(): string;


	public function getArgs(): array;


	public function getType(): Type;


	/**
	 * @param mixed|null $context
	 * @return mixed
	 */
	public function resolve(array $root, array $args, $context = null);

}
