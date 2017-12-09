<?php

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
	private $mutationFieldProvider;


	protected function setUp()
	{
		$this->mutationFieldProvider = new MutationFieldsProvider;
	}


	public function testAddField()
	{
		$mutationField = $this->getMutationField();

		$this->assertEmpty($this->mutationFieldProvider->getFields());

		$this->mutationFieldProvider->addField($mutationField);

		$this->assertCount(1, $this->mutationFieldProvider->getFields());
	}


	/**
	 * @expectedException \Portiny\GraphQL\Exception\Provider\ExistingMutationFieldException
	 * @expectedExceptionMessage Mutation field with name "Some name" is already registered.
	 */
	public function testAddFieldAlreadyExists()
	{
		$mutationField = $this->getMutationField();

		$this->assertEmpty($this->mutationFieldProvider->getFields());

		$this->mutationFieldProvider->addField($mutationField);
		$this->mutationFieldProvider->addField($mutationField);
	}


	public function testGetFields()
	{
		$mutationField = $this->getMutationField();
		$this->mutationFieldProvider->addField($mutationField);

		$fields = $this->mutationFieldProvider->getFields();
		$this->assertCount(1, $fields);
		$this->assertSame($mutationField, reset($fields));
	}


	public function testConvertFieldsToArray()
	{
		$mutationField = $this->getMutationField();
		$this->mutationFieldProvider->addField($mutationField);

		$output = $this->mutationFieldProvider->convertFieldsToArray();
		$this->assertSame('Some name', key($output));

		$mutationFieldAsArray = reset($output);
		$this->assertInstanceOf(StringType::class, $mutationFieldAsArray['type']);
		$this->assertSame('Some description', $mutationFieldAsArray['description']);
		$this->assertArrayHasKey('someArg', $mutationFieldAsArray['args']);
		$this->assertArrayHasKey('type', $mutationFieldAsArray['args']['someArg']);
		$this->assertInstanceOf(StringType::class, $mutationFieldAsArray['args']['someArg']['type']);
		$this->assertTrue(is_callable($mutationFieldAsArray['resolve']));

		$output = $this->mutationFieldProvider->convertFieldsToArray([SomeMutationField::class]);
		$this->assertEmpty($output);
	}


	private function getMutationField(): MutationFieldInterface
	{
		return (new class () implements MutationFieldInterface
		{

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
			public function getType(): Type
			{
				return Type::string();
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
					'someArg' => ['type' => Type::string()]
				];
			}


			/**
			 * {@inheritdoc}
			 */
			public function resolve(array $root, array $args, $context = NULL)
			{
				return 'resolved';
			}

		});
	}

}
