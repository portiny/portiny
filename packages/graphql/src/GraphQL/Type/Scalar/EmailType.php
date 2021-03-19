<?php declare(strict_types = 1);

namespace Portiny\GraphQL\GraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use UnexpectedValueException;

class EmailType extends ScalarType
{
	/**
	 * {@inheritdoc}
	 */
	public $name = 'Email';

	/**
	 * {@inheritdoc}
	 */
	public $description = 'This scalar type represents e-mail formatted string.';


	/**
	 * {@inheritdoc}
	 */
	public function serialize($value)
	{
		return $value;
	}


	/**
	 * {@inheritdoc}
	 */
	public function parseValue($value)
	{
		if ($value !== null && $value !== '' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
			throw new UnexpectedValueException('Cannot represent value as email: ' . Utils::printSafe($value));
		}

		return $value;
	}


	/**
	 * {@inheritdoc}
	 */
	public function parseLiteral($valueNode, ?array $variables = null)
	{
		if (! $valueNode instanceof StringValueNode) {
			$kind = isset($valueNode->kind) ? $valueNode->kind : '';
			throw new Error('Can only parse strings got: ' . $kind, $valueNode);
		}

		if (! filter_var($valueNode->value, FILTER_VALIDATE_EMAIL)) {
			throw new Error('Not a valid email: ' . Utils::printSafe($valueNode->value), $valueNode);
		}

		return $valueNode->value;
	}

}
