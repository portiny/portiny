<?php

namespace Portiny\GraphQL\Tests\Converter;

use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\Converter\QueryFieldConverter;
use Portiny\GraphQL\Tests\AbstractContainerTestCase;


class QueryFieldConverterTest extends AbstractContainerTestCase
{

	public function testToArray()
	{
		$queryField = $this->getQueryField();
		$output = QueryFieldConverter::toArray($queryField);

		$this->assertSame('Some name', key($output));

		$queryFieldAsArray = reset($output);
		$this->assertInstanceOf(StringType::class, $queryFieldAsArray['type']);
		$this->assertSame('Some description', $queryFieldAsArray['description']);
		$this->assertArrayHasKey('someArg', $queryFieldAsArray['args']);
		$this->assertArrayHasKey('type', $queryFieldAsArray['args']['someArg']);
		$this->assertInstanceOf(StringType::class, $queryFieldAsArray['args']['someArg']['type']);
		$this->assertTrue(is_callable($queryFieldAsArray['resolve']));
	}


	public function testToObject()
	{
		$queryField = $this->getQueryField();
		$queryFieldAsArray = QueryFieldConverter::toArray($queryField);
		$output = QueryFieldConverter::toObject($queryFieldAsArray);

		$this->assertSame('Some name', $output->getName());
		$this->assertInstanceOf(StringType::class, $output->getType());
		$this->assertSame('Some description', $output->getDescription());
		$this->assertArrayHasKey('someArg', $output->getArgs());
		$this->assertArrayHasKey('type', $output->getArgs()['someArg']);
		$this->assertInstanceOf(StringType::class, $output->getArgs()['someArg']['type']);
		$this->assertSame('resolved', $output->resolve([], ['someArg' => '']));
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
