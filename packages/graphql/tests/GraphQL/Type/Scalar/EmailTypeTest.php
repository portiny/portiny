<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\GraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\StringValueNode;
use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\GraphQL\Type\Scalar\EmailType;
use UnexpectedValueException;

final class EmailTypeTest extends TestCase
{
	public function testSerialize(): void
	{
		$emailType = new EmailType();

		self::assertSame('test@test.test', $emailType->serialize('test@test.test'));
	}

	public function testParseValue(): void
	{
		$emailType = new EmailType();

		self::assertSame('test@test.test', $emailType->parseValue('test@test.test'));
	}

	public function testParseValueNotValidEmail(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$this->expectExceptionMessage('Cannot represent value as email: test');

		$emailType = new EmailType();

		self::assertSame('test', $emailType->parseValue('test'));
	}

	public function testParseLiteral(): void
	{
		$emailType = new EmailType();
		$stringValueNode = new StringValueNode(['value' => 'test@test.test']);

		self::assertSame('test@test.test', $emailType->parseLiteral($stringValueNode));
	}

	public function testParseLiteralNotValidEmail(): void
	{
		$this->expectException(Error::class);
		$this->expectExceptionMessage('Not a valid email');

		$emailType = new EmailType();
		$stringValueNode = new StringValueNode(['value' => 'test']);

		self::assertSame('test', $emailType->parseLiteral($stringValueNode));
	}

	public function testParseLiteralNotValidNode(): void
	{
		$this->expectException(Error::class);
		$this->expectExceptionMessage('Can only parse strings got: BooleanValue');

		$emailType = new EmailType();
		$booleanValueNode = new BooleanValueNode(['value' => null]);

		self::assertSame('test', $emailType->parseLiteral($booleanValueNode));
	}
}
