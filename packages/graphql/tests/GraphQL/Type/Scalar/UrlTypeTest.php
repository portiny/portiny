<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\GraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\StringValueNode;
use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\GraphQL\Type\Scalar\UrlType;
use UnexpectedValueException;

final class UrlTypeTest extends TestCase
{
	public function testSerialize(): void
	{
		$urlType = new UrlType();

		self::assertSame('https://portiny.org', $urlType->serialize('https://portiny.org'));
	}

	public function testParseValue(): void
	{
		$urlType = new UrlType();

		self::assertSame('https://portiny.org', $urlType->parseValue('https://portiny.org'));
	}

	public function testParseValueNotValidUrl(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$this->expectExceptionMessage('Cannot represent value as URL: test');

		$urlType = new UrlType();

		self::assertSame('test', $urlType->parseValue('test'));
	}

	public function testParseLiteral(): void
	{
		$urlType = new UrlType();
		$stringValueNode = new StringValueNode(['value' => 'https://portiny.org']);

		self::assertSame('https://portiny.org', $urlType->parseLiteral($stringValueNode));
	}

	public function testParseLiteralNotValidUrl(): void
	{
		$this->expectException(Error::class);
		$this->expectExceptionMessage('Not a valid URL');

		$urlType = new UrlType();
		$stringValueNode = new StringValueNode(['value' => 'test']);

		self::assertSame('test', $urlType->parseLiteral($stringValueNode));
	}

	public function testParseLiteralNotValidNode(): void
	{
		$this->expectException(Error::class);
		$this->expectExceptionMessage('Can only parse strings got: BooleanValue');

		$urlType = new UrlType();
		$booleanValueNode = new BooleanValueNode(['value' => null]);

		self::assertSame('test', $urlType->parseLiteral($booleanValueNode));
	}
}
