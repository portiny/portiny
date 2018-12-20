<?php
declare(strict_types=1);

namespace Portiny\RabbitMQ\Tests\Queue;

use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Queue\AbstractQueue;
use Portiny\RabbitMQ\Queue\QueueBind;

final class AbstractQueueTest extends TestCase
{
	/**
	 * @var AbstractQueue
	 */
	private $testQueue;

	protected function setUp(): void
	{
		$this->testQueue = $this->createTestQueue();
	}

	public function testGetName(): void
	{
		$this->assertSame('myName', $this->testQueue->getName());
	}

	public function testIsPasive(): void
	{
		$this->assertFalse($this->testQueue->isPassive());
	}

	public function testIsDurable(): void
	{
		$this->assertTrue($this->testQueue->isDurable());
	}

	public function testIsExclusive(): void
	{
		$this->assertFalse($this->testQueue->isExclusive());
	}

	public function testIsAutoDelete(): void
	{
		$this->assertFalse($this->testQueue->isAutoDelete());
	}

	public function testIsNoWait(): void
	{
		$this->assertFalse($this->testQueue->isNoWait());
	}

	public function testGetArguments(): void
	{
		$this->assertSame(['b' => 2], $this->testQueue->getArguments());
	}

	public function testGetBindings(): void
	{
		$bindings = $this->testQueue->getBindings();
		/** @var QueueBind $firstBind */
		$firstBind = reset($bindings);

		$this->assertCount(1, $bindings);
		$this->assertInstanceOf(QueueBind::class, $firstBind);
		$this->assertSame('exchangeName', $firstBind->getExchange());
	}

	private function createTestQueue(): AbstractQueue
	{
		return new class() extends AbstractQueue {
			public function getName(): string
			{
				return 'myName';
			}

			public function isPassive(): bool
			{
				return false;
			}

			public function isDurable(): bool
			{
				return true;
			}

			public function isExclusive(): bool
			{
				return false;
			}

			public function isAutoDelete(): bool
			{
				return false;
			}

			public function isNoWait(): bool
			{
				return false;
			}

			public function getArguments(): array
			{
				return ['b' => 2];
			}

			public function getBindings(): array
			{
				return [new QueueBind('exchangeName')];
			}
		};
	}
}
