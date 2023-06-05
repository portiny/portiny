<?php declare(strict_types = 1);

namespace Portiny\GraphQL\GraphQL\Type\Scalar;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class DateTimeType extends ScalarType
{
	/**
	 * {@inheritdoc}
	 */
	public string $name = 'DateTime';

	/**
	 * {@inheritdoc}
	 */
	public ?string $description = 'This scalar type represents time data, represented as an ISO-8601 encoded UTC date string.';


	/**
	 * {@inheritdoc}
	 */
	public function serialize($value): string
	{
		if (! $value instanceof DateTimeInterface) {
			$printedValue = Utils::printSafe($value);
			throw new InvariantViolation('DateTime is not an instance of DateTimeImmutable: ' . $printedValue);
		}

		return $value->format(DateTime::ATOM);
	}


	/**
	 * {@inheritdoc}
	 */
	public function parseValue($value): ?DateTimeImmutable
	{
		return DateTimeImmutable::createFromFormat(DateTime::ATOM, $value) ?: null;
	}


	/**
	 * {@inheritdoc}
	 */
    public function parseLiteral(Node $valueNode, array $variables = null)
	{
		if ($valueNode instanceof StringValueNode) {
			return $valueNode->value;
		}

		return null;
	}

}
