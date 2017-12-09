<?php

declare(strict_types = 1);

namespace Portiny\GraphQL\GraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use UnexpectedValueException;


final class EmailType extends ScalarType
{

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
		if ( ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
			throw new UnexpectedValueException('Cannot represent value as email: ' . Utils::printSafe($value));
		}

		return $value;
	}


	/**
	 * {@inheritdoc}
	 */
	public function parseLiteral($valueNode)
	{
		if ( ! $valueNode instanceof StringValueNode) {
			throw new Error('Can only parse strings got: ' . $valueNode->kind, [$valueNode]);
		}
		if ( ! filter_var($valueNode->value, FILTER_VALIDATE_EMAIL)) {
			throw new Error('Not a valid email', [$valueNode]);
		}

		return $valueNode->value;
	}

}
