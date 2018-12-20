<?php
declare(strict_types=1);

namespace Portiny\RabbitMQ\Tests\Queue;

use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Queue\QueueBind;

final class QueueBindTest extends TestCase
{
	/**
	 * @var QueueBind
	 */
	private $queueBind;

	protected function setUp(): void
	{
		$this->queueBind = new QueueBind('someExchange', 'routingKey', true, ['b' => 2]);
	}

	public function testGetExchange(): void
	{
		$this->assertSame('someExchange', $this->queueBind->getExchange());
	}

	public function testGetRoutingKey(): void
	{
		$this->assertSame('routingKey', $this->queueBind->getRoutingKey());
	}

	public function testIsNowait(): void
	{
		$this->assertTrue($this->queueBind->isNowait());
	}

	public function testGetArguments(): void
	{
		$this->assertSame(['b' => 2], $this->queueBind->getArguments());
	}
}
