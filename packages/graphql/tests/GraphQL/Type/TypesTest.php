<?php declare(strict_types=1);

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

		self::assertInstanceOf(EmailType::class, $emailType);
		self::assertInstanceOf(EmailType::class, $sameEmailType);
		self::assertSame($emailType, $sameEmailType);
	}
}
