<?php

declare(strict_types=1);

namespace Portiny\GraphQL\GraphQL\Type;

use GraphQL\Type\Definition\Type;

final class Types
{
	/**
	 * @var Type[]
	 */
	private static $types = [];

	public static function get(string $className): Type
	{
		if (! isset(self::$types[$className])) {
			self::$types[$className] = new $className();
		}

		return self::$types[$className];
	}

	public static function findByName(string $name): ?Type
	{
		foreach (self::$types as $type) {
			if ($type->name === $name) {
				return $type;
			}
		}

		return null;
	}

	public static function getTypeClasses(): array
	{
		$typeClasses = [];
		foreach (self::$types as $type) {
			$typeClasses[] = get_class($type);
		}

		return $typeClasses;
	}

	public static function loadTypesFromClasses(array $classes): void
	{
		foreach ($classes as $class) {
			self::get($class);
		}
	}
}
