<?php
declare(strict_types=1);

namespace Portiny\RabbitMQ\Tests\Exchange;

use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Exchange\AbstractExchange;
use Portiny\RabbitMQ\Exchange\ExchangeBind;

final class AbstractExchangeTest extends TestCase
{
	/**
	 * @var AbstractExchange
	 */
	private $testExchange;

	protected function setUp(): void
	{
		$this->testExchange = $this->createTestExchange();
	}

	public function testGetName(): void
	{
		$this->assertSame('myName', $this->testExchange->getName());
	}

	public function testGetBindings(): void
	{
		$bindings = $this->testExchange->getBindings();
		/** @var ExchangeBind $firstBind */
		$firstBind = reset($bindings);

		$this->assertCount(1, $bindings);
		$this->assertInstanceOf(ExchangeBind::class, $firstBind);
		$this->assertSame('someDestination', $firstBind->getDestination());
	}

	public function testGetType(): void
	{
		$this->assertSame(AbstractExchange::TYPE_FANOUT, $this->testExchange->getType());
	}

	public function testIsPasive(): void
	{
		$this->assertFalse($this->testExchange->isPassive());
	}

	public function testIsDurable(): void
	{
		$this->assertTrue($this->testExchange->isDurable());
	}

	public function testIsAutoDelete(): void
	{
		$this->assertFalse($this->testExchange->isAutoDelete());
	}

	public function testIsInternal(): void
	{
		$this->assertTrue($this->testExchange->isInternal());
	}

	public function testIsNoWait(): void
	{
		$this->assertFalse($this->testExchange->isNoWait());
	}

	public function testGetArguments(): void
	{
		$this->assertSame(['b' => 2], $this->testExchange->getArguments());
	}

	private function createTestExchange(): AbstractExchange
	{
		return new class() extends AbstractExchange {
			public function getName(): string
			{
				return 'myName';
			}

			public function getBindings(): array
			{
				return [new ExchangeBind('someDestination')];
			}

			public function getType(): string
			{
				return AbstractExchange::TYPE_FANOUT;
			}

			public function isPassive(): bool
			{
				return false;
			}

			public function isDurable(): bool
			{
				return true;
			}

			public function isAutoDelete(): bool
			{
				return false;
			}

			public function isInternal(): bool
			{
				return true;
			}

			public function isNoWait(): bool
			{
				return false;
			}

			public function getArguments(): array
			{
				return ['b' => 2];
			}
		};
	}
}
