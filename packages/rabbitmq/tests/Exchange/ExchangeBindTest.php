<?php
declare(strict_types=1);

namespace Portiny\RabbitMQ\Tests\Exchange;

use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Exchange\ExchangeBind;

final class ExchangeBindTest extends TestCase
{
	/**
	 * @var ExchangeBind
	 */
	private $exchangeBind;

	protected function setUp(): void
	{
		$this->exchangeBind = new ExchangeBind('someDestination', 'routingKey', true, ['a' => 1]);
	}

	public function testGetDestination(): void
	{
		self::assertSame('someDestination', $this->exchangeBind->getDestination());
	}

	public function testGetRoutingKey(): void
	{
		self::assertSame('routingKey', $this->exchangeBind->getRoutingKey());
	}

	public function testIsNowait(): void
	{
		self::assertTrue($this->exchangeBind->isNowait());
	}

	public function testGetArguments(): void
	{
		self::assertSame(['a' => 1], $this->exchangeBind->getArguments());
	}
}
