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

		$this->assertEmpty($this->mutationFieldsProvider->getFields());

		$this->mutationFieldsProvider->addField($mutationField);

		$this->assertCount(1, $this->mutationFieldsProvider->getFields());
	}

	/**
	 * @expectedException \Portiny\GraphQL\Exception\Provider\ExistingMutationFieldException
	 * @expectedExceptionMessage Mutation field with name "Some name" is already registered.
	 */
	public function testAddFieldAlreadyExists(): void
	{
		$mutationField = $this->getMutationField();

		$this->assertEmpty($this->mutationFieldsProvider->getFields());

		$this->mutationFieldsProvider->addField($mutationField);
		$this->mutationFieldsProvider->addField($mutationField);
	}

	public function testGetFields(): void
	{
		$mutationField = $this->getMutationField();
		$this->mutationFieldsProvider->addField($mutationField);

		$fields = $this->mutationFieldsProvider->getFields();
		$this->assertCount(1, $fields);
		$this->assertSame($mutationField, reset($fields));
	}

	public function testConvertFieldsToArray(): void
	{
		$mutationField = $this->getMutationField();
		$this->mutationFieldsProvider->addField($mutationField);

		$output = $this->mutationFieldsProvider->convertFieldsToArray();
		$this->assertSame('Some name', key($output));

		$mutationFieldAsArray = reset($output);
		$this->assertInstanceOf(StringType::class, $mutationFieldAsArray['type']);
		$this->assertSame('Some description', $mutationFieldAsArray['description']);
		$this->assertArrayHasKey('someArg', $mutationFieldAsArray['args']);
		$this->assertArrayHasKey('type', $mutationFieldAsArray['args']['someArg']);
		$this->assertInstanceOf(StringType::class, $mutationFieldAsArray['args']['someArg']['type']);
		$this->assertTrue(is_callable($mutationFieldAsArray['resolve']));

		$output = $this->mutationFieldsProvider->convertFieldsToArray([SomeMutationField::class]);
		$this->assertEmpty($output);
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
			public function resolve(array $root, array $args, $context = NULL)
			{
				return 'resolved';
			}
		};
	}
}
