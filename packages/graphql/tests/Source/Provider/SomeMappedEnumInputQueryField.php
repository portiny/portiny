<?php declare(strict_types = 1);

namespace Portiny\GraphQL\Tests\Source\Provider;

use GraphQL\Type\Definition\Type;
use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\GraphQL\Type\Types;
use Portiny\GraphQL\Tests\Source\Type\SomeMappedEnumType;

final class SomeMappedEnumInputQueryField implements QueryFieldInterface
{

	public function getName(): string
	{
		return 'mappedEnumInput';
	}


	public function getDescription(): string
	{
		return 'Returns the internal value received from a mapped enum';
	}


	public function getArgs(): array
	{
		return [
			'value' => [
				'type' => Type::nonNull(Types::get(SomeMappedEnumType::class)),
			],
		];
	}


	public function getType(): Type
	{
		return Type::string();
	}


	public function resolve(array $root, array $args, $context = null)
	{
		return $args['value'];
	}

}
