<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\Converter;

use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\Contract\Mutation\MutationFieldInterface;
use Portiny\GraphQL\Converter\MutationFieldConverter;

final class MutationFieldConverterTest extends TestCase
{
	public function testToArray(): void
	{
		$mutationField = $this->getMutationField();
		$output = MutationFieldConverter::toArray($mutationField);

		self::assertSame('Some name', key($output));

		$mutationFieldAsArray = reset($output);
		self::assertInstanceOf(StringType::class, $mutationFieldAsArray['type']);
		self::assertSame('Some description', $mutationFieldAsArray['description']);
		self::assertArrayHasKey('someArg', $mutationFieldAsArray['args']);
		self::assertArrayHasKey('type', $mutationFieldAsArray['args']['someArg']);
		self::assertInstanceOf(StringType::class, $mutationFieldAsArray['args']['someArg']['type']);
		self::assertTrue(is_callable($mutationFieldAsArray['resolve']));
	}

	public function testToObject(): void
	{
		$mutationField = $this->getMutationField();
		$mutationFieldAsArray = MutationFieldConverter::toArray($mutationField);
		$output = MutationFieldConverter::toObject($mutationFieldAsArray);

		self::assertSame('Some name', $output->getName());
		self::assertInstanceOf(StringType::class, $output->getType());
		self::assertSame('Some description', $output->getDescription());
		self::assertArrayHasKey('someArg', $output->getArgs());
		self::assertArrayHasKey('type', $output->getArgs()['someArg']);
		self::assertInstanceOf(StringType::class, $output->getArgs()['someArg']['type']);
		self::assertSame('resolved', $output->resolve([], ['someArg' => '']));
	}

	private function getMutationField(): MutationFieldInterface
	{
		return new class() implements MutationFieldInterface {
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
