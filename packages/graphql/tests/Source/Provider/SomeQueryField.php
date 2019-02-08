<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\Source\Provider;

use GraphQL\Type\Definition\Type;
use Portiny\GraphQL\Contract\Field\QueryFieldInterface;

final class SomeQueryField implements QueryFieldInterface
{
	/**
	 * {@inheritdoc}
	 */
	public function getName(): string
	{
		return 'someQueryName';
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
		return 'resolved ' . $args['someArg'];
	}
}
