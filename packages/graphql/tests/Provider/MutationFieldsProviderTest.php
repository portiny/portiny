<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\Provider;

use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\Contract\Mutation\MutationFieldInterface;
use Portiny\GraphQL\Contract\Provider\MutationFieldsProviderInterface;
use Portiny\GraphQL\Provider\MutationFieldsProvider;
use Portiny\GraphQL\Tests\Source\Provider\SomeMutationField;

class MutationFieldsProviderTest extends TestCase
{
	/**
	 * @var MutationFieldsProviderInterface
	 */
	private $mutationFieldsProvider;

	protected function setUp(): void
	{
		$this->mutationFieldsProvider = new MutationFieldsProvider();
	}

	public function testAddField(): void
	{
		$mutationField = $this->getMutationField();

		self::assertEmpty($this->mutationFieldsProvider->getFields());

		$this->mutationFieldsProvider->addField($mutationField);

		self::assertCount(1, $this->mutationFieldsProvider->getFields());
	}

	/**
	 * @expectedException \Portiny\GraphQL\Exception\Provider\ExistingMutationFieldException
	 * @expectedExceptionMessage Mutation field with name "Some name" is already registered.
	 */
	public function testAddFieldAlreadyExists(): void
	{
		$mutationField = $this->getMutationField();

		self::assertEmpty($this->mutationFieldsProvider->getFields());

		$this->mutationFieldsProvider->addField($mutationField);
		$this->mutationFieldsProvider->addField($mutationField);
	}

	public function testGetFields(): void
	{
		$mutationField = $this->getMutationField();
		$this->mutationFieldsProvider->addField($mutationField);

		$fields = $this->mutationFieldsProvider->getFields();
		self::assertCount(1, $fields);
		self::assertSame($mutationField, reset($fields));
	}

	public function testConvertFieldsToArray(): void
	{
		$mutationField = $this->getMutationField();
		$this->mutationFieldsProvider->addField($mutationField);

		$output = $this->mutationFieldsProvider->convertFieldsToArray();
		self::assertSame('Some name', key($output));

		$mutationFieldAsArray = reset($output);
		self::assertInstanceOf(StringType::class, $mutationFieldAsArray['type']);
		self::assertSame('Some description', $mutationFieldAsArray['description']);
		self::assertArrayHasKey('someArg', $mutationFieldAsArray['args']);
		self::assertArrayHasKey('type', $mutationFieldAsArray['args']['someArg']);
		self::assertInstanceOf(StringType::class, $mutationFieldAsArray['args']['someArg']['type']);
		self::assertTrue(is_callable($mutationFieldAsArray['resolve']));

		$output = $this->mutationFieldsProvider->convertFieldsToArray([SomeMutationField::class]);
		self::assertEmpty($output);
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
				return 'resolved';
			}
		};
	}
}
