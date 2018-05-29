<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\GraphQL\Type\Scalar;

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

		$this->assertSame('https://portiny.org', $urlType->serialize('https://portiny.org'));
	}

	public function testParseValue(): void
	{
		$urlType = new UrlType();

		$this->assertSame('https://portiny.org', $urlType->parseValue('https://portiny.org'));
	}

	/**
	 * @expectedException UnexpectedValueException
	 * @expectedExceptionMessage Cannot represent value as URL: test
	 */
	public function testParseValueNotValidUrl(): void
	{
		$urlType = new UrlType();

		$this->assertSame('test', $urlType->parseValue('test'));
	}

	public function testParseLiteral(): void
	{
		$urlType = new UrlType();
		$stringValueNode = new StringValueNode(['value' => 'https://portiny.org']);

		$this->assertSame('https://portiny.org', $urlType->parseLiteral($stringValueNode));
	}

	/**
	 * @expectedException \GraphQL\Error\Error
	 * @expectedExceptionMessage Not a valid URL
	 */
	public function testParseLiteralNotValidUrl(): void
	{
		$urlType = new UrlType();
		$stringValueNode = new StringValueNode(['value' => 'test']);

		$this->assertSame('test', $urlType->parseLiteral($stringValueNode));
	}

	/**
	 * @expectedException \GraphQL\Error\Error
	 * @expectedExceptionMessage Can only parse strings got: BooleanValue
	 */
	public function testParseLiteralNotValidNode(): void
	{
		$urlType = new UrlType();
		$booleanValueNode = new BooleanValueNode(['value' => FALSE]);

		$this->assertSame('test', $urlType->parseLiteral($booleanValueNode));
	}
}
