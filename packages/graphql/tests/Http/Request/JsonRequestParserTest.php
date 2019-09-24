<?php declare(strict_types = 1);

namespace Portiny\GraphQL\Tests\Http\Request;

use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\Http\Request\JsonRequestParser;

final class JsonRequestParserTest extends TestCase
{

	public function testGetQuery(): void
	{
		$jsonRequestParser = new JsonRequestParser('{"query": "some query", "variables": {}}');

		self::assertSame('some query', $jsonRequestParser->getQuery());
	}


	public function testGetVariables(): void
	{
		$jsonRequestParser = new JsonRequestParser('{"query": "some query", "variables": {"key": "value"}}');

		self::assertSame(['key' => 'value'], $jsonRequestParser->getVariables());
	}


	public function testEmptyData(): void
	{
		$jsonRequestParser = new JsonRequestParser('');

		self::assertSame('', $jsonRequestParser->getQuery());
		self::assertSame([], $jsonRequestParser->getVariables());
	}

}
