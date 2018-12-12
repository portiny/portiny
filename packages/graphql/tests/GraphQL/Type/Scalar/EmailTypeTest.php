<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\GraphQL\Type\Scalar;

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

		$this->assertSame('test@test.test', $emailType->serialize('test@test.test'));
	}

	public function testParseValue(): void
	{
		$emailType = new EmailType();

		$this->assertSame('test@test.test', $emailType->parseValue('test@test.test'));
	}

	/**
	 * @expectedException UnexpectedValueException
	 * @expectedExceptionMessage Cannot represent value as email: test
	 */
	public function testParseValueNotValidEmail(): void
	{
		$emailType = new EmailType();

		$this->assertSame('test', $emailType->parseValue('test'));
	}

	public function testParseLiteral(): void
	{
		$emailType = new EmailType();
		$stringValueNode = new StringValueNode(['value' => 'test@test.test']);

		$this->assertSame('test@test.test', $emailType->parseLiteral($stringValueNode));
	}

	/**
	 * @expectedException \GraphQL\Error\Error
	 * @expectedExceptionMessage Not a valid email
	 */
	public function testParseLiteralNotValidEmail(): void
	{
		$emailType = new EmailType();
		$stringValueNode = new StringValueNode(['value' => 'test']);

		$this->assertSame('test', $emailType->parseLiteral($stringValueNode));
	}

	/**
	 * @expectedException \GraphQL\Error\Error
	 * @expectedExceptionMessage Can only parse strings got: BooleanValue
	 */
	public function testParseLiteralNotValidNode(): void
	{
		$emailType = new EmailType();
		$booleanValueNode = new BooleanValueNode(['value' => null]);

		$this->assertSame('test', $emailType->parseLiteral($booleanValueNode));
	}
}
