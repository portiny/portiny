<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Producer;

use Bunny\Channel;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Tests\Source\TestProducer;

final class AbstractProducerProduceWithDelayTest extends TestCase
{

	private TestProducer $producer;

	/** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
	private $channel;


	protected function setUp(): void
	{
		parent::setUp();

		$this->producer = new TestProducer();
		$this->channel = $this->createMock(Channel::class);
	}


	public function testNonPositiveDelayDelegatesToProduce(): void
	{
		$this->channel->expects(self::once())
			->method('publish')
			->with(
				'body',
				[],
				'exchangeName',
				'routingKey',
				false,
				false
			)
			->willReturn(true);

		$this->channel->expects(self::never())->method('exchangeDeclare');
		$this->channel->expects(self::never())->method('queueDeclare');
		$this->channel->expects(self::never())->method('queueBind');

		$this->producer->produceWithDelay($this->channel, 'body', 0);
	}


	public function testNegativeDelayDelegatesToProduce(): void
	{
		$this->channel->expects(self::once())
			->method('publish')
			->with(
				'body',
				[],
				'exchangeName',
				'routingKey',
				false,
				false
			)
			->willReturn(true);

		$this->channel->expects(self::never())->method('exchangeDeclare');
		$this->channel->expects(self::never())->method('queueDeclare');
		$this->channel->expects(self::never())->method('queueBind');

		$this->producer->produceWithDelay($this->channel, 'body', -500);
	}


	public function testPositiveDelayNeverCallsExchangeDeclare(): void
	{
		// delays exchange is declared once at startup by BunnyManager::declareExchanges(),
		// not on every publish call.
		$this->channel->expects(self::never())->method('exchangeDeclare');

		$this->channel->method('queueDeclare');
		$this->channel->method('queueBind');
		$this->channel->method('publish')->willReturn(true);

		$this->producer->produceWithDelay($this->channel, 'body', 60000);
	}


	public function testPositiveDelayDeclaresCorrectlyNamedQueue(): void
	{
		$delayMs = 60000;
		$expectedQueueName = sprintf('delay_%s_%s_%d', 'exchangeName', 'routingKey', $delayMs);

		$this->channel->expects(self::once())
			->method('queueDeclare')
			->with(
				$expectedQueueName,
				false,
				true,
				false,
				false,
				false,
				[
					'x-message-ttl' => $delayMs,
					'x-expires' => $delayMs + 10000,
					'x-dead-letter-exchange' => 'exchangeName',
					'x-dead-letter-routing-key' => 'routingKey',
				]
			);

		$this->channel->method('queueBind');
		$this->channel->method('publish')->willReturn(true);

		$this->producer->produceWithDelay($this->channel, 'body', $delayMs);
	}


	public function testPositiveDelayBindsQueueToDelaysExchange(): void
	{
		$delayMs = 5000;
		$expectedQueueName = sprintf('delay_%s_%s_%d', 'exchangeName', 'routingKey', $delayMs);

		$this->channel->method('queueDeclare');

		$this->channel->expects(self::once())
			->method('queueBind')
			->with($expectedQueueName, BunnyManager::DELAYS_EXCHANGE, $expectedQueueName);

		$this->channel->method('publish')->willReturn(true);

		$this->producer->produceWithDelay($this->channel, 'body', $delayMs);
	}


	public function testPositiveDelayPublishesToDelaysExchangeWithQueueAsRoutingKey(): void
	{
		$delayMs = 1000;
		$expectedQueueName = sprintf('delay_%s_%s_%d', 'exchangeName', 'routingKey', $delayMs);

		$this->channel->method('queueDeclare');
		$this->channel->method('queueBind');

		$this->channel->expects(self::once())
			->method('publish')
			->with(
				'body',
				[],
				BunnyManager::DELAYS_EXCHANGE,
				$expectedQueueName,
				false,
				false
			)
			->willReturn(true);

		$this->producer->produceWithDelay($this->channel, 'body', $delayMs);
	}


	public function testExpiresIsDelayPlusTenSeconds(): void
	{
		$delayMs = 900000;

		/** @var array<string, int>|null $capturedQueueArgs */
		$capturedQueueArgs = null;
		$this->channel->expects(self::once())
			->method('queueDeclare')
			->willReturnCallback(
				static function (
					string $queue,
					bool $passive,
					bool $durable,
					bool $exclusive,
					bool $autoDelete,
					bool $nowait,
					array $arguments
				) use (&$capturedQueueArgs): void {
					/** @var array<string, int> $arguments */
					$capturedQueueArgs = $arguments;
				}
			);

		$this->channel->method('queueBind');
		$this->channel->method('publish')->willReturn(true);

		$this->producer->produceWithDelay($this->channel, 'body', $delayMs);

		self::assertIsArray($capturedQueueArgs);
		/** @var array<string, int> $assertArgs */
		$assertArgs = $capturedQueueArgs;
		self::assertSame($delayMs, $assertArgs['x-message-ttl']);
		self::assertSame($delayMs + 10000, $assertArgs['x-expires']);
	}

}
