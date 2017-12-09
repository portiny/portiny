<?php

declare(strict_types = 1);

namespace Portiny\GraphQL\GraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use UnexpectedValueException;


final class UrlType extends ScalarType
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
		if ( ! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
			throw new UnexpectedValueException('Cannot represent value as URL: ' . Utils::printSafe($value));
		}

		return $value;
	}


	/**
	 * {@inheritdoc}
	 */
	public function parseLiteral($ast)
	{
		if ( ! $ast instanceof StringValueNode) {
			throw new Error('Can only parse strings got: ' . $ast->kind, [$ast]);
		}
		if ( ! is_string($ast->value) || ! filter_var($ast->value, FILTER_VALIDATE_URL)) {
			throw new Error('Not a valid URL', [$ast]);
		}

		return $ast->value;
	}

}
