<?php

declare(strict_types=1);

namespace Portiny\GraphQL\Converter;

use GraphQL\Type\Definition\Type;
use Portiny\GraphQL\Contract\Mutation\MutationFieldInterface;

class MutationFieldConverter
{
	public static function toArray(MutationFieldInterface $mutationField): array
	{
		return [
			$mutationField->getName() => [
				'type' => $mutationField->getType(),
				'description' => $mutationField->getDescription(),
				'args' => $mutationField->getArgs(),
				'resolve' => function ($root, $args, $context) use ($mutationField) {
					return call_user_func_array([$mutationField, 'resolve'], [$root, $args, $context]);
				},
			],
		];
	}

	public static function toObject(array $data): MutationFieldInterface
	{
		return new class($data) implements MutationFieldInterface {
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
				$this->name = key($data);
				$this->data = reset($data);
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
			public function getType(): Type
			{
				return $this->data['type'];
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
			public function resolve(array $root, array $args, $context = NULL)
			{
				return $this->data['resolve']($root, $args, $context);
			}
		};
	}
}
