<?php

namespace Portiny\GraphQL\Tests\Provider;

use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\Contract\Provider\QueryFieldsProviderInterface;
use Portiny\GraphQL\Provider\QueryFieldsProvider;
use Portiny\GraphQL\Tests\Source\Provider\SomeQueryField;


class QueryFieldsProviderTest extends TestCase
{

	/**
	 * @var QueryFieldsProviderInterface
	 */
	private $queryFieldsProvider;


	protected function setUp()
	{
		$this->queryFieldsProvider = new QueryFieldsProvider;
	}


	public function testAddField()
	{
		$queryField = $this->getQueryField();

		$this->assertEmpty($this->queryFieldsProvider->getFields());

		$this->queryFieldsProvider->addField($queryField);

		$this->assertCount(1, $this->queryFieldsProvider->getFields());
	}


	/**
	 * @expectedException \Portiny\GraphQL\Exception\Provider\ExistingQueryFieldException
	 * @expectedExceptionMessage Query field with name "Some name" is already registered.
	 */
	public function testAddFieldAlreadyExists()
	{
		$queryField = $this->getQueryField();

		$this->assertEmpty($this->queryFieldsProvider->getFields());

		$this->queryFieldsProvider->addField($queryField);
		$this->queryFieldsProvider->addField($queryField);
	}


	public function testGetFields()
	{
		$queryField = $this->getQueryField();
		$this->queryFieldsProvider->addField($queryField);

		$fields = $this->queryFieldsProvider->getFields();
		$this->assertCount(1, $fields);
		$this->assertSame($queryField, reset($fields));
	}


	public function testConvertFieldsToArray()
	{
		$queryField = $this->getQueryField();
		$this->queryFieldsProvider->addField($queryField);

		$output = $this->queryFieldsProvider->convertFieldsToArray();
		$this->assertSame('Some name', key($output));

		$queryFieldAsArray = reset($output);
		$this->assertInstanceOf(StringType::class, $queryFieldAsArray['type']);
		$this->assertSame('Some description', $queryFieldAsArray['description']);
		$this->assertArrayHasKey('someArg', $queryFieldAsArray['args']);
		$this->assertArrayHasKey('type', $queryFieldAsArray['args']['someArg']);
		$this->assertInstanceOf(StringType::class, $queryFieldAsArray['args']['someArg']['type']);
		$this->assertTrue(is_callable($queryFieldAsArray['resolve']));

		$output = $this->queryFieldsProvider->convertFieldsToArray([SomeQueryField::class]);
		$this->assertEmpty($output);
	}


	private function getQueryField(): QueryFieldInterface
	{
		return (new class () implements QueryFieldInterface
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
