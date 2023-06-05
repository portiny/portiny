<?php declare(strict_types = 1);

namespace Portiny\GraphQL\GraphQL\Type;


final class Types
{
    /**
     * @var array<string, mixed>
     */
	private static $types = [];


    /**
     * @template T
     * @param class-string<T> $className
     * @return T
     */
    public static function get(string $className)
    {
        return self::$types[$className] ??= new $className();
    }


    /**
     * @return mixed|null
     */
	public static function findByName(string $name)
	{
		foreach (self::$types as $type) {
			if ($type->name === $name) {
				return $type;
			}
		}

		return null;
	}


    /**
     * @return class-string[]
     */
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
