<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Tests\Command;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Exception\ClientException;
use PHPUnit\Framework\TestCase;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Command\ConsumeCommand;
use Portiny\RabbitMQ\ConnectionRegistry;
use Portiny\RabbitMQ\Tests\Source\TestConsumer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests for ConsumeCommand reconnect loop.
 *
 * BunnyManager is final, so we cannot subclass it. We inject pre-built Client/Channel
 * mock doubles into its private properties via Reflection so no live broker is required.
 *
 * The primary coverage here is: (a) a ClientException from run() triggers the retry
 * path and emits the expected log lines; (b) --max-reconnect-attempts bounds the loop
 * correctly and returns exit code 1; (c) unknown consumers still return -1 unchanged.
 *
 * Testing the "reconnect succeeds and process continues" path end-to-end would require
 * a live broker because reconnect() nulls the memoized client, causing the next
 * getChannel() → createChannel() → connect() call to open a real socket. That scenario
 * is covered at the integration / smoke level (e.g. a manually triggered broker restart
 * while a consumer is running). The unit tests here isolate the branching logic.
 */
final class ConsumeCommandReconnectTest extends TestCase
{

	/**
	 * Build a BunnyManager whose memoized client and channel properties have been
	 * replaced with the supplied test doubles via Reflection. The manager is configured
	 * with the "myAlias" alias pointing to TestConsumer.
	 */
	private function makeManager(Client $client, Channel $channel): BunnyManager
	{
		$manager = new BunnyManager(
			[
				'host' => '127.0.0.10',
				'port' => 9999,
				'user' => 'guest',
				'password' => 'guest',
				'vhost' => '/',
				'timeout' => 1,
				'heartbeat' => 60.0,
				'persistent' => false,
				'path' => '/',
				'tcp_nodelay' => false,
			],
			['myAlias' => 'Portiny\\RabbitMQ\\Tests\\Source\\TestConsumer'],
			[new TestConsumer()],
			[],
			[]
		);

		// Inject doubles directly into private properties so getClient()/getChannel()
		// return them without touching the network on the first iteration.
		$this->injectProperty($manager, 'client', $client);
		$this->injectProperty($manager, 'channel', $channel);

		return $manager;
	}


	/**
	 * Verify that a ClientException thrown by run() is caught and the "Transport error"
	 * log line is written. With --max-reconnect-attempts=1 the loop exhausts immediately
	 * after the first failure (attempt counter reaches the limit before the "Reconnecting"
	 * line, which is intentional — there is nothing to reconnect to) and exits with code 1.
	 */
	public function testClientExceptionTriggersTransportErrorLogAndExhaustsAttempts(): void
	{
		/** @var Channel&\PHPUnit\Framework\MockObject\MockObject $channel */
		$channel = $this->createMock(Channel::class);
		$channel->method('consume')->willReturn(null);
		$channel->method('qos')->willReturn(null);

		/** @var Client&\PHPUnit\Framework\MockObject\MockObject $client */
		$client = $this->createMock(Client::class);
		$client->method('run')->willThrowException(
			new ClientException('Broken pipe or closed connection.')
		);

		$manager = $this->makeManager($client, $channel);
		$registry = new ConnectionRegistry(['default' => $manager], 'default');

		$command = new ConsumeCommand($registry);
		$command->setName('rabbitmq:consume');

		$input = new ArrayInput([
			'consumer' => 'myAlias',
			'--max-reconnect-attempts' => '1',
			'--reconnect-base-delay' => '0',
		]);
		$input->setInteractive(false);

		$output = new BufferedOutput();

		$exitCode = $command->run($input, $output);

		self::assertSame(1, $exitCode);

		$outputText = $output->fetch();
		self::assertStringContainsString('Transport error', $outputText);
		self::assertStringContainsString('Broken pipe or closed connection', $outputText);
		self::assertStringContainsString('Maximum reconnect attempts (1) reached', $outputText);
	}


	public function testMaxReconnectAttemptsIsHonored(): void
	{
		/** @var Channel&\PHPUnit\Framework\MockObject\MockObject $channel */
		$channel = $this->createMock(Channel::class);
		$channel->method('consume')->willReturn(null);
		$channel->method('qos')->willReturn(null);

		/** @var Client&\PHPUnit\Framework\MockObject\MockObject $client */
		$client = $this->createMock(Client::class);
		$client->method('run')->willThrowException(new ClientException('Connection closed by server'));

		$manager = $this->makeManager($client, $channel);
		$registry = new ConnectionRegistry(['default' => $manager], 'default');

		$command = new ConsumeCommand($registry);
		$command->setName('rabbitmq:consume');

		$input = new ArrayInput([
			'consumer' => 'myAlias',
			'--max-reconnect-attempts' => '2',
			'--reconnect-base-delay' => '0',
		]);
		$input->setInteractive(false);

		$output = new BufferedOutput();

		$exitCode = $command->run($input, $output);

		self::assertSame(1, $exitCode);

		$outputText = $output->fetch();
		self::assertStringContainsString('Maximum reconnect attempts (2) reached', $outputText);
	}


	/**
	 * A broker-initiated graceful close (CONNECTION_FORCED on a node drain / rolling restart)
	 * while a consumer channel is still open makes Bunny\Client::disconnect() throw a plain
	 * \LogicException("All channels have to be closed by now.") — NOT a ClientException. This is
	 * the exact failure that crashed conviu-core consumers during the nightly kured reboot window:
	 * the old catch (ClientException) missed it, so it escaped the reconnect loop. It must now be
	 * recognised as a transport error and follow the same retry path.
	 */
	public function testBrokerInitiatedGracefulCloseIsTreatedAsTransportError(): void
	{
		/** @var Channel&\PHPUnit\Framework\MockObject\MockObject $channel */
		$channel = $this->createMock(Channel::class);
		$channel->method('consume')->willReturn(null);
		$channel->method('qos')->willReturn(null);

		/** @var Client&\PHPUnit\Framework\MockObject\MockObject $client */
		$client = $this->createMock(Client::class);
		$client->method('run')->willThrowException(
			new \LogicException('All channels have to be closed by now.')
		);

		$manager = $this->makeManager($client, $channel);
		$registry = new ConnectionRegistry(['default' => $manager], 'default');

		$command = new ConsumeCommand($registry);
		$command->setName('rabbitmq:consume');

		$input = new ArrayInput([
			'consumer' => 'myAlias',
			'--max-reconnect-attempts' => '1',
			'--reconnect-base-delay' => '0',
		]);
		$input->setInteractive(false);

		$output = new BufferedOutput();

		$exitCode = $command->run($input, $output);

		self::assertSame(1, $exitCode);

		$outputText = $output->fetch();
		self::assertStringContainsString('Transport error', $outputText);
		self::assertStringContainsString('All channels have to be closed by now', $outputText);
		self::assertStringContainsString('Maximum reconnect attempts (1) reached', $outputText);
	}


	/**
	 * A genuine error raised by the consumer's own message handler (application code, not the
	 * transport) must NOT be swallowed by the reconnect loop — otherwise a poison message would be
	 * retried forever. It has to propagate out of the command unchanged.
	 */
	public function testApplicationHandlerErrorPropagatesAndIsNotRetried(): void
	{
		/** @var Channel&\PHPUnit\Framework\MockObject\MockObject $channel */
		$channel = $this->createMock(Channel::class);
		$channel->method('consume')->willReturn(null);
		$channel->method('qos')->willReturn(null);

		/** @var Client&\PHPUnit\Framework\MockObject\MockObject $client */
		$client = $this->createMock(Client::class);
		$client->method('run')->willThrowException(
			new \RuntimeException('handler blew up on a malformed message')
		);

		$manager = $this->makeManager($client, $channel);
		$registry = new ConnectionRegistry(['default' => $manager], 'default');

		$command = new ConsumeCommand($registry);
		$command->setName('rabbitmq:consume');

		$input = new ArrayInput([
			'consumer' => 'myAlias',
			'--max-reconnect-attempts' => '5',
			'--reconnect-base-delay' => '0',
		]);
		$input->setInteractive(false);

		$output = new BufferedOutput();

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('handler blew up on a malformed message');

		$command->run($input, $output);
	}


	public function testConsumerNotFoundReturnsMinusOne(): void
	{
		/** @var Client&\PHPUnit\Framework\MockObject\MockObject $client */
		$client = $this->createMock(Client::class);
		/** @var Channel&\PHPUnit\Framework\MockObject\MockObject $channel */
		$channel = $this->createMock(Channel::class);

		$manager = $this->makeManager($client, $channel);
		$registry = new ConnectionRegistry(['default' => $manager], 'default');

		$command = new ConsumeCommand($registry);
		$command->setName('rabbitmq:consume');

		$input = new ArrayInput(['consumer' => 'nonExistingConsumer']);
		$input->setInteractive(false);

		$output = new BufferedOutput();

		$exitCode = $command->run($input, $output);

		self::assertSame(-1, $exitCode);
	}


	/**
	 * @param mixed $value
	 */
	private function injectProperty(BunnyManager $object, string $property, $value): void
	{
		$ref = new \ReflectionProperty($object, $property);
		$ref->setAccessible(true);
		$ref->setValue($object, $value);
	}

}
