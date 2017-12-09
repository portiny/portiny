<?php

namespace Portiny\GraphQL\Tests\GraphQL\Type\Scalar;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\StringValueNode;
use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\GraphQL\Type\Scalar\EmailType;
use UnexpectedValueException;


class EmailTypeTest extends TestCase
{

	public function testSerialize()
	{
		$emailType = new EmailType;

		$this->assertSame('test@test.test', $emailType->serialize('test@test.test'));
	}

	public function testParseValue()
	{
		$emailType = new EmailType;

		$this->assertSame('test@test.test', $emailType->parseValue('test@test.test'));
	}


	/**
	 * @expectedException UnexpectedValueException
	 * @expectedExceptionMessage Cannot represent value as email: "test"
	 */
	public function testParseValueNotValidEmail()
	{
		$emailType = new EmailType;

		$this->assertSame('test', $emailType->parseValue('test'));
	}


	public function testParseLiteral()
	{
		$emailType = new EmailType;
		$stringValueNode = new StringValueNode(['value' => 'test@test.test']);

		$this->assertSame('test@test.test', $emailType->parseLiteral($stringValueNode));
	}


	/**
	 * @expectedException \GraphQL\Error\Error
	 * @expectedExceptionMessage Not a valid email
	 */
	public function testParseLiteralNotValidEmail()
	{
		$emailType = new EmailType;
		$stringValueNode = new StringValueNode(['value' => 'test']);

		$this->assertSame('test', $emailType->parseLiteral($stringValueNode));
	}


	/**
	 * @expectedException \GraphQL\Error\Error
	 * @expectedExceptionMessage Can only parse strings got: BooleanValue
	 */
	public function testParseLiteralNotValidNode()
	{
		$emailType = new EmailType;
		$booleanValueNode = new BooleanValueNode(['value' => FALSE]);

		$this->assertSame('test', $emailType->parseLiteral($booleanValueNode));
	}

}
