<?php

namespace Portiny\GraphQL\Tests\GraphQL\Type;

use PHPUnit\Framework\TestCase;
use Portiny\GraphQL\GraphQL\Type\Scalar\EmailType;
use Portiny\GraphQL\GraphQL\Type\Types;

final class TypesTest extends TestCase
{
	public function testGet(): void
	{
		$emailType = Types::get(EmailType::class);
		$sameEmailType = Types::get(EmailType::class);

		$this->assertInstanceOf(EmailType::class, $emailType);
		$this->assertInstanceOf(EmailType::class, $sameEmailType);
		$this->assertSame($emailType, $sameEmailType);
	}
}
