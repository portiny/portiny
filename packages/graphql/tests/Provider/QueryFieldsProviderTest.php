<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\Provider;

use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\Contract\Provider\QueryFieldsProviderInterface;
use Portiny\GraphQL\Exception\Provider\ExistingQueryFieldException;
use Portiny\GraphQL\Provider\QueryFieldsProvider;
use Portiny\GraphQL\Tests\Source\Provider\SomeQueryField;

class QueryFieldsProviderTest extends TestCase
{
	/**
	 * @var QueryFieldsProviderInterface
	 */
	private $queryFieldsProvider;

	protected function setUp(): void
	{
		$this->queryFieldsProvider = new QueryFieldsProvider();
	}

	public function testAddField(): void
	{
		$queryField = $this->getQueryField();

		self::assertEmpty($this->queryFieldsProvider->getFields());

		$this->queryFieldsProvider->addField($queryField);

		self::assertCount(1, $this->queryFieldsProvider->getFields());
	}

	public function testAddFieldAlreadyExists(): void
	{
		$this->expectException(ExistingQueryFieldException::class);
		$this->expectExceptionMessage('Query field with name "Some name" is already registered.');

		$queryField = $this->getQueryField();

		self::assertEmpty($this->queryFieldsProvider->getFields());

		$this->queryFieldsProvider->addField($queryField);
		$this->queryFieldsProvider->addField($queryField);
	}

	public function testGetFields(): void
	{
		$queryField = $this->getQueryField();
		$this->queryFieldsProvider->addField($queryField);

		$fields = $this->queryFieldsProvider->getFields();
		self::assertCount(1, $fields);
		self::assertSame($queryField, reset($fields));
	}

	public function testConvertFieldsToArray(): void
	{
		$queryField = $this->getQueryField();
		$this->queryFieldsProvider->addField($queryField);

		$output = $this->queryFieldsProvider->convertFieldsToArray();
		self::assertSame('Some name', key($output));

		$queryFieldAsArray = reset($output);
		self::assertInstanceOf(StringType::class, $queryFieldAsArray['type']);
		self::assertSame('Some description', $queryFieldAsArray['description']);
		self::assertArrayHasKey('someArg', $queryFieldAsArray['args']);
		self::assertArrayHasKey('type', $queryFieldAsArray['args']['someArg']);
		self::assertInstanceOf(StringType::class, $queryFieldAsArray['args']['someArg']['type']);
		self::assertTrue(is_callable($queryFieldAsArray['resolve']));

		$output = $this->queryFieldsProvider->convertFieldsToArray([SomeQueryField::class]);
		self::assertEmpty($output);
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
