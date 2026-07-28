<?php declare(strict_types = 1);

namespace Portiny\GraphQL\Tests\GraphQL\Schema;

use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\Contract\Http\Request\RequestParserInterface;
use Portiny\GraphQL\GraphQL\RequestProcessor;
use Portiny\GraphQL\GraphQL\Schema\SchemaCacheProvider;
use Portiny\GraphQL\Http\Request\JsonRequestParser;
use Portiny\GraphQL\Provider\MutationFieldsProvider;
use Portiny\GraphQL\Provider\QueryFieldsProvider;
use Portiny\GraphQL\Tests\Source\Provider\SomeMappedEnumInputQueryField;
use Portiny\GraphQL\Tests\Source\Provider\SomeMappedEnumOutputQueryField;

final class SchemaCacheProviderTest extends TestCase
{
	/**
	 * @var string
	 */
	private $cacheDir;


	protected function setUp(): void
	{
		parent::setUp();

		$this->cacheDir = sys_get_temp_dir() . '/portiny-graphql-schema-cache-' . bin2hex(random_bytes(8));
	}


	protected function tearDown(): void
	{
		foreach (glob($this->cacheDir . '/*') ?: [] as $file) {
			unlink($file);
		}
		if (is_dir($this->cacheDir)) {
			rmdir($this->cacheDir);
		}

		parent::tearDown();
	}


	public function testMappedEnumValuesSurviveSchemaCacheRoundTrip(): void
	{
		$request = $this->createRequest();

		$uncachedOutput = $this->createRequestProcessor()->process($request);
		$cachedOutput = $this->createRequestProcessor()->process($request);

		$expected = [
			'data' => [
				'mappedEnumInput' => 'internal-value',
				'mappedEnumOutput' => 'VISIBLE_NAME',
			],
		];
		self::assertSame($expected, $uncachedOutput);
		self::assertSame($expected, $cachedOutput);
	}


	private function createRequest(): RequestParserInterface
	{
		return new JsonRequestParser(json_encode([
			'query' => 'query Test($value: SomeMappedEnum!) {'
				. ' mappedEnumInput(value: $value) mappedEnumOutput }',
			'variables' => [
				'value' => 'VISIBLE_NAME',
			],
		], JSON_THROW_ON_ERROR));
	}


	private function createRequestProcessor(): RequestProcessor
	{
		$queryFieldsProvider = new QueryFieldsProvider([
			new SomeMappedEnumInputQueryField(),
			new SomeMappedEnumOutputQueryField(),
		]);
		$mutationFieldsProvider = new MutationFieldsProvider();
		$schemaCacheProvider = new SchemaCacheProvider(
			$this->cacheDir,
			$queryFieldsProvider,
			$mutationFieldsProvider
		);
		$requestProcessor = new RequestProcessor(
			false,
			$mutationFieldsProvider,
			$queryFieldsProvider,
			$schemaCacheProvider
		);
		$requestProcessor->setSchemaCache(true);

		return $requestProcessor;
	}

}
