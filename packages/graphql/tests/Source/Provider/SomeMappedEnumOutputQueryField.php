<?php declare(strict_types = 1);

namespace Portiny\GraphQL\Tests\Source\Provider;

use GraphQL\Type\Definition\Type;
use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\GraphQL\Type\Types;
use Portiny\GraphQL\Tests\Source\Type\SomeMappedEnumType;

final class SomeMappedEnumOutputQueryField implements QueryFieldInterface
{

	public function getName(): string
	{
		return 'mappedEnumOutput';
	}


	public function getDescription(): string
	{
		return 'Returns an internal mapped enum value';
	}


	public function getArgs(): array
	{
		return [];
	}


	public function getType(): Type
	{
		return Types::get(SomeMappedEnumType::class);
	}


	public function resolve(array $root, array $args, $context = null)
	{
		return SomeMappedEnumType::INTERNAL_VALUE;
	}

}
