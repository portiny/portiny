<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\GraphQL;

use Nette\Http\Request;
use Nette\Http\UrlScript;
use Portiny\GraphQL\Contract\Http\Request\RequestParserInterface;
use Portiny\GraphQL\GraphQL\RequestProcessor;
use Portiny\GraphQL\GraphQL\Schema\SchemaCacheProvider;
use Portiny\GraphQL\Http\Request\JsonRequestParser;
use Portiny\GraphQL\Provider\MutationFieldsProvider;
use Portiny\GraphQL\Provider\QueryFieldsProvider;
use Portiny\GraphQL\Tests\AbstractContainerTestCase;
use Portiny\GraphQL\Tests\Source\Provider\SomeMutationField;
use Portiny\GraphQL\Tests\Source\Provider\SomeQueryField;

final class RequestProcessorTest extends AbstractContainerTestCase
{
	/**
	 * {@inheritdoc}
	 */
	protected function setUp(): void
	{
		parent::setUp();
	}

	public function testProcess(): void
	{
		// test query
		$rawData = '{"query": "query Test($someArg: String) {'
			. 'someQueryName(someArg: $someArg)}", "variables": {"someArg": "someValue"}}';

		$requestParser = $this->createRequestParser($rawData);
		$output = $this->createRequestFactory()
			->process($requestParser);

		self::assertTrue(is_array($output));
		self::assertSame('resolved someValue', $output['data']['someQueryName']);

		// test mutation
		$rawData = '{"query": "mutation Test($someArg: String) {'
			. 'someMutationName(someArg: $someArg)}", "variables": {"someArg": "someValue"}}';
		$requestParser = $this->createRequestParser($rawData);

		$output = $this->createRequestFactory()
			->process($requestParser);

		self::assertTrue(is_array($output));
		self::assertSame('someValue resolved', $output['data']['someMutationName']);
	}

	private function createRequestParser(string $rawData): RequestParserInterface
	{
		$url = new UrlScript('https://portiny.org');
		$httpRequest = new Request($url, null, null, null, null, null, null, null, null, function () use ($rawData) {
			return $rawData;
		});

		return new JsonRequestParser($httpRequest);
	}

	private function createRequestFactory(): RequestProcessor
	{
		$queryField = new SomeQueryField();
		$queryFieldsProvider = new QueryFieldsProvider();
		$queryFieldsProvider->addField($queryField);

		$mutationField = new SomeMutationField();
		$mutationFieldsProvider = new MutationFieldsProvider();
		$mutationFieldsProvider->addField($mutationField);

		$schemaCacheProvider = new SchemaCacheProvider('', $queryFieldsProvider, $mutationFieldsProvider);

		return new RequestProcessor($mutationFieldsProvider, $queryFieldsProvider, $schemaCacheProvider);
	}
}
