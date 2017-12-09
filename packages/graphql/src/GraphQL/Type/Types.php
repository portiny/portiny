<?php

declare(strict_types=1);

namespace Portiny\GraphQL\GraphQL\Type;

use GraphQL\Type\Definition\Type;

final class Types
{
	/**
	 * @var array
	 */
	private static $types = [];

	public static function get(string $className): Type
	{
		if (! isset(self::$types[$className])) {
			self::$types[$className] = new $className();
		}

		return self::$types[$className];
	}
}
