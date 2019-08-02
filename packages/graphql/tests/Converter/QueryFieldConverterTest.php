<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\Converter;

use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\Converter\QueryFieldConverter;

final class QueryFieldConverterTest extends TestCase
{
	public function testToArray(): void
	{
		$queryField = $this->getQueryField();
		$output = QueryFieldConverter::toArray($queryField);

		self::assertSame('Some name', key($output));

		$queryFieldAsArray = reset($output);
		self::assertInstanceOf(StringType::class, $queryFieldAsArray['type']);
		self::assertSame('Some description', $queryFieldAsArray['description']);
		self::assertArrayHasKey('someArg', $queryFieldAsArray['args']);
		self::assertArrayHasKey('type', $queryFieldAsArray['args']['someArg']);
		self::assertInstanceOf(StringType::class, $queryFieldAsArray['args']['someArg']['type']);
		self::assertTrue(is_callable($queryFieldAsArray['resolve']));
	}

	public function testToObject(): void
	{
		$queryField = $this->getQueryField();
		$queryFieldAsArray = QueryFieldConverter::toArray($queryField);
		$output = QueryFieldConverter::toObject($queryFieldAsArray);

		self::assertSame('Some name', $output->getName());
		self::assertInstanceOf(StringType::class, $output->getType());
		self::assertSame('Some description', $output->getDescription());
		self::assertArrayHasKey('someArg', $output->getArgs());
		self::assertArrayHasKey('type', $output->getArgs()['someArg']);
		self::assertInstanceOf(StringType::class, $output->getArgs()['someArg']['type']);
		self::assertSame('resolved', $output->resolve([], ['someArg' => '']));
	}

	private function getQueryField(): QueryFieldInterface
	{
		return new class() implements QueryFieldInterface {
			/**
			 * {@inheritdoc}
			 */
			public function getName(): string
			{
				return 'Some name';
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
					'someArg' => [
						'type' => Type::string(),
					],
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
				return 'resolved';
			}
		};
	}
}
