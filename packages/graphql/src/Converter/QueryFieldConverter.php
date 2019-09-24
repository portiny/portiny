<?php declare(strict_types = 1);

namespace Portiny\GraphQL\Converter;

use GraphQL\Type\Definition\Type;
use Portiny\GraphQL\Contract\Field\QueryFieldInterface;

final class QueryFieldConverter
{

	public static function toArray(QueryFieldInterface $queryField): array
	{
		return [
			$queryField->getName() => [
				'type' => $queryField->getType(),
				'description' => $queryField->getDescription(),
				'args' => $queryField->getArgs(),
				'resolve' => function ($root, $args, $context) use ($queryField) {
					return call_user_func_array([$queryField, 'resolve'], [$root, $args, $context]);
				},
			],
		];
	}


	public static function toObject(array $data): QueryFieldInterface
	{
		return new class($data) implements QueryFieldInterface {
			/**
			 * @var string
			 */
			private $name;

			/**
			 * @var array
			 */
			private $data = [];


			public function __construct(array $data)
			{
				$this->name = (string) key($data);
				$this->data = (array) reset($data);
			}


			/**
			 * {@inheritdoc}
			 */
			public function getName(): string
			{
				return $this->name;
			}


			/**
			 * {@inheritdoc}
			 */
			public function getDescription(): string
			{
				return $this->data['description'];
			}


			/**
			 * {@inheritdoc}
			 */
			public function getArgs(): array
			{
				return $this->data['args'];
			}


			/**
			 * {@inheritdoc}
			 */
			public function getType(): Type
			{
				return $this->data['type'];
			}


			/**
			 * {@inheritdoc}
			 */
			public function resolve(array $root, array $args, $context = null)
			{
				return $this->data['resolve']($root, $args, $context);
			}

		};
	}

}
