<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\Http\Request;

use Nette\Http\Request;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\Http\Request\JsonRequestParser;

final class JsonRequestParserTest extends TestCase
{
	public function testGetQuery(): void
	{
		$url = new UrlScript('https://portiny.org');
		$httpRequest = new Request($url, null, null, null, null, null, null, null, null, function () {
			return '{"query": "some query", "variables": {}}';
		});
		$jsonRequestParser = new JsonRequestParser($httpRequest);

		self::assertSame('some query', $jsonRequestParser->getQuery());
	}

	public function testGetVariables(): void
	{
		$url = new UrlScript('https://portiny.org');
		$httpRequest = new Request($url, null, null, null, null, null, null, null, null, function () {
			return '{"query": "some query", "variables": {"key": "value"}}';
		});
		$jsonRequestParser = new JsonRequestParser($httpRequest);

		self::assertSame(['key' => 'value'], $jsonRequestParser->getVariables());
	}

	public function testEmptyData(): void
	{
		$url = new UrlScript('https://portiny.org');
		$httpRequest = new Request($url, null, null, null, null, null, null, null, null, function () {
			return '';
		});
		$jsonRequestParser = new JsonRequestParser($httpRequest);

		self::assertSame('', $jsonRequestParser->getQuery());
		self::assertSame([], $jsonRequestParser->getVariables());
	}
}
