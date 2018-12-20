<?php
declare(strict_types=1);

namespace Portiny\RabbitMQ\Tests\Producer;

use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Producer\AbstractProducer;

final class AbstractProducerTest extends TestCase
{
	/**
	 * @var AbstractProducer
	 */
	private $testProducer;

	protected function setUp(): void
	{
		$this->testProducer = $this->createTestProducer();
	}

	public function testGetExchangeName(): void
	{
		$this->assertSame('exchangeName', $this->testProducer->getExchangeName());
	}

	public function testGetRouting(): void
	{
		$this->assertSame('routingKey', $this->testProducer->getRoutingKey());
	}

	public function testGetHeaders(): void
	{
		$this->assertSame(['c' => 3], $this->testProducer->getHeaders());
	}

	public function testIsMandatory(): void
	{
		$this->assertFalse($this->testProducer->isMandatory());
	}

	public function testIsImmediate(): void
	{
		$this->assertTrue($this->testProducer->isImmediate());
	}

	private function createTestProducer(): AbstractProducer
	{
		return new class() extends AbstractProducer {
			public function getExchangeName(): string
			{
				return 'exchangeName';
			}

			public function getRoutingKey(): string
			{
				return 'routingKey';
			}

			public function getHeaders(): array
			{
				return ['c' => 3];
			}

			public function isMandatory(): bool
			{
				return false;
			}

			public function isImmediate(): bool
			{
				return true;
			}
		};
	}
}
