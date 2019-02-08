<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\Source\Provider;

use GraphQL\Type\Definition\Type;
use Portiny\GraphQL\Contract\Mutation\MutationFieldInterface;

final class SomeMutationField implements MutationFieldInterface
{
	/**
	 * {@inheritdoc}
	 */
	public function getName(): string
	{
		return 'someMutationName';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDescription(): string
	{
		return 'Some description';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getArgs(): array
	{
		return [
			'someArg' => ['type' => Type::string()],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getType(): Type
	{
		return Type::string();
	}

	/**
	 * {@inheritdoc}
	 */
	public function resolve(array $root, array $args, $context = null)
	{
		return $args['someArg'] . ' resolved';
	}
}
