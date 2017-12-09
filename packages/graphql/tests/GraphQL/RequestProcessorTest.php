<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\GraphQL;

use Nette\Http\Request;
use Nette\Http\UrlScript;
use Portiny\GraphQL\GraphQL\RequestProcessor;
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
		$output = $this->createRequestFactory($rawData)
			->process();

		$this->assertTrue(is_array($output));
		$this->assertSame('resolved someValue', $output['data']['someQueryName']);

		// test mutation
		$rawData = '{"query": "mutation Test($someArg: String) {'
			. 'someMutationName(someArg: $someArg)}", "variables": {"someArg": "someValue"}}';
		$output = $this->createRequestFactory($rawData)
			->process();

		$this->assertTrue(is_array($output));
		$this->assertSame('someValue resolved', $output['data']['someMutationName']);
	}

	protected function createRequestFactory(string $rawData): RequestProcessor
	{
		$url = new UrlScript('https://portiny.org');
		$httpRequest = new Request($url, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, function () use ($rawData) {
			return $rawData;
		});
		$jsonRequestParser = new JsonRequestParser($httpRequest);

		$queryField = new SomeQueryField();
		$queryFieldProvider = new QueryFieldsProvider();
		$queryFieldProvider->addField($queryField);

		$mutationField = new SomeMutationField();
		$mutationFieldProvider = new MutationFieldsProvider();
		$mutationFieldProvider->addField($mutationField);

		$requestProcessor = new RequestProcessor($jsonRequestParser);
		$requestProcessor->setQueryFieldsProvider($queryFieldProvider);
		$requestProcessor->setMutationFieldsProvider($mutationFieldProvider);

		return $requestProcessor;
	}
}
