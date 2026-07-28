<?php declare(strict_types = 1);

namespace Portiny\GraphQL\Tests\Source\Type;

use GraphQL\Type\Definition\EnumType;

final class SomeMappedEnumType extends EnumType
{
	public const INTERNAL_VALUE = 'internal-value';


	public function __construct()
	{
		parent::__construct([
			'name' => 'SomeMappedEnum',
			'values' => [
				'VISIBLE_NAME' => [
					'value' => self::INTERNAL_VALUE,
				],
			],
		]);
	}

}
