<?php
declare(strict_types=1);

namespace Portiny\RabbitMQ\Tests\Consumer;

use Bunny\Message;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;

final class AbstractConsumerTest extends TestCase
{
	/**
	 * @var AbstractConsumer
	 */
	private $testConsumer;

	protected function setUp(): void
	{
		$this->testConsumer = $this->createTestConsumer();
	}

	public function testGetQueueName(): void
	{
		$this->assertSame('queueName', $this->testConsumer->getQueueName());
	}

	public function testProcess(): void
	{
		$message = new Message('', '', false, '', '', [], '1');
		$this->assertSame(AbstractConsumer::MESSAGE_ACK, $this->testConsumer->process($message));
	}

	public function testGetConsumerTag(): void
	{
		$this->assertSame('consumerTag', $this->testConsumer->getConsumerTag());
	}

	public function testIsNoLocal(): void
	{
		$this->assertTrue($this->testConsumer->isNoLocal());
	}

	public function testIsNoAck(): void
	{
		$this->assertFalse($this->testConsumer->isNoAck());
	}

	public function testIsExclusive(): void
	{
		$this->assertTrue($this->testConsumer->isExclusive());
	}

	public function testIsNoWait(): void
	{
		$this->assertTrue($this->testConsumer->isNoWait());
	}

	public function testGetArguments(): void
	{
		$this->assertSame(['a' => 1], $this->testConsumer->getArguments());
	}

	public function testGetPrefetchSize(): void
	{
		$this->assertSame(1, $this->testConsumer->getPrefetchSize());
	}

	public function testGetPrefetchCount(): void
	{
		$this->assertSame(5, $this->testConsumer->getPrefetchCount());
	}

	private function createTestConsumer(): AbstractConsumer
	{
		return new class() extends AbstractConsumer {
			public function getQueueName(): string
			{
				return 'queueName';
			}

			public function process(Message $message): int
			{
				$data = $message->content;

				if ($data === '1') {
					return self::MESSAGE_ACK;
				} elseif ($data === '2') {
					return self::MESSAGE_NACK;
				}
				return self::MESSAGE_REJECT;
			}

			public function getConsumerTag(): string
			{
				return 'consumerTag';
			}

			public function isNoLocal(): bool
			{
				return true;
			}

			public function isNoAck(): bool
			{
				return false;
			}

			public function isExclusive(): bool
			{
				return true;
			}

			public function isNoWait(): bool
			{
				return true;
			}

			public function getArguments(): array
			{
				return ['a' => 1];
			}

			public function getPrefetchSize(): int
			{
				return 1;
			}

			public function getPrefetchCount(): int
			{
				return 5;
			}
		};
	}
}
