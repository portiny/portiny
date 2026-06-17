<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Command;

use Bunny\Channel;
use Bunny\Exception\ClientException;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\ConnectionRegistry;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'rabbitmq:consume',
	description: 'Run a RabbitMQ consumer'
)]
final class ConsumeCommand extends Command
{

	/**
	 * Base delay in seconds for the first reconnect backoff.
	 */
	private const RECONNECT_BASE_DELAY_DEFAULT = 1;

	/**
	 * Maximum delay in seconds between reconnect attempts (caps the exponential growth).
	 */
	private const RECONNECT_MAX_DELAY = 30;

	/**
	 * Non-ClientException throwables that Bunny raises from its own teardown path and that we
	 * must treat as recoverable transport errors. When the broker initiates a graceful close
	 * (CONNECTION_FORCED on a node drain / rolling restart) while a consumer channel still has
	 * an in-flight delivery, Bunny\Client::disconnect() throws a plain \LogicException with this
	 * exact message instead of a ClientException — so the type check alone misses it. This is a
	 * version-independent fallback; the primary detection is the bunny/bunny origin check.
	 */
	private const BUNNY_TRANSPORT_ERROR_MESSAGES = [
		'All channels have to be closed by now.',
	];

	/**
	 * @var ConnectionRegistry
	 */
	private $connectionRegistry;


	public function __construct(ConnectionRegistry $connectionRegistry)
	{
		parent::__construct();

		$this->connectionRegistry = $connectionRegistry;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function configure(): void
	{
		$this->setName('rabbitmq:consume')
			->setDescription('Run a RabbitMQ consumer')
			->addArgument('consumer', InputArgument::REQUIRED, 'FQDN or alias of the consumer')
			->addOption('messages', 'm', InputOption::VALUE_OPTIONAL, 'Amount of messages to consume')
			->addOption('time', 't', InputOption::VALUE_OPTIONAL, 'Max seconds for consumer to run')
			->addOption('connection', 'c', InputOption::VALUE_OPTIONAL, 'Name of the RabbitMQ connection')
			->addOption(
				'max-reconnect-attempts',
				null,
				InputOption::VALUE_OPTIONAL,
				'Maximum reconnect attempts (0 = unlimited within the time budget)',
				0
			)
			->addOption(
				'reconnect-base-delay',
				null,
				InputOption::VALUE_OPTIONAL,
				'Base delay in seconds for exponential reconnect backoff',
				self::RECONNECT_BASE_DELAY_DEFAULT
			);
	}


	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		/** @var string $consumerName */
		$consumerName = $input->getArgument('consumer');
		$numberOfMessages = $this->getNumberOfMessages($input);
		$secondsToRun = $this->getSecondsToRun($input);
		$connectionName = $this->getConnectionName($input);
		$maxAttempts = $this->getMaxReconnectAttempts($input);
		$baseDelay = $this->getReconnectBaseDelay($input);

		$output->writeln(
			sprintf(
				'<comment>[%s]</comment> <info>Starting consumer "%s"...</info>',
				date('Y-m-d H:i:s'),
				$consumerName
			)
		);

		try {
			$resolved = $this->resolveConsumer($consumerName, $connectionName);
		} catch (\InvalidArgumentException $exception) {
			$output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
			return -1;
		}

		if ($resolved === null) {
			$output->writeln('<error>Consumer not found!</error>');
			return -1;
		}

		[$bunnyManager, $consumer] = $resolved;

		// Wall-clock deadline; null means run indefinitely (until -m is satisfied or SIGTERM).
		$deadline = $secondsToRun !== null ? (microtime(true) + $secondsToRun) : null;

		$attempts = 0;
		$currentDelay = $baseDelay;

		while (true) {
			try {
				/** @var Channel $channel */
				$channel = $bunnyManager->getChannel();

				$output->writeln('<info>Consuming...</info>');

				$consumer->consume($channel, $numberOfMessages);

				$remaining = $deadline !== null ? max(0.0, $deadline - microtime(true)) : null;

				$bunnyManager->getClient()->run($remaining !== null ? (int) ceil($remaining) : null);

				// run() returned normally: either the time budget elapsed or -m was satisfied.
				return 0;

			} catch (\Throwable $exception) {
				// A genuine error from the consumer's own message handler originates in
				// application code and MUST propagate — never silently retry it forever. Only
				// transport-layer failures (the AMQP connection broke, including a broker-initiated
				// graceful close during a node drain) are reconnectable.
				if (! $this->isReconnectableTransportError($exception)) {
					throw $exception;
				}

				$output->writeln(
					sprintf(
						'<comment>[%s]</comment> <error>Transport error: %s</error>',
						date('Y-m-d H:i:s'),
						$exception->getMessage()
					)
				);

				// Honor wall-clock deadline: if the budget is already exhausted, stop cleanly.
				if ($deadline !== null && microtime(true) >= $deadline) {
					return 0;
				}

				$attempts++;
				if ($maxAttempts > 0 && $attempts >= $maxAttempts) {
					$output->writeln(
						sprintf(
							'<error>Maximum reconnect attempts (%d) reached. Giving up.</error>',
							$maxAttempts
						)
					);
					return 1;
				}

				// Capped exponential backoff with 0–20 % additive jitter.
				$jitter = (int) ($currentDelay * 0.2 * (mt_rand(0, 100) / 100));
				$sleepSeconds = min($currentDelay + $jitter, self::RECONNECT_MAX_DELAY);

				// Never sleep past the wall-clock deadline.
				if ($deadline !== null) {
					$remaining = max(0.0, $deadline - microtime(true));
					$sleepSeconds = min($sleepSeconds, (int) ceil($remaining));
				}

				$output->writeln(
					sprintf(
						'<comment>[%s]</comment> <info>Reconnecting in %d second(s) (attempt %d)…</info>',
						date('Y-m-d H:i:s'),
						$sleepSeconds,
						$attempts
					)
				);

				if ($sleepSeconds > 0) {
					sleep($sleepSeconds);
				}

				// Re-check deadline after sleeping.
				if ($deadline !== null && microtime(true) >= $deadline) {
					return 0;
				}

				$bunnyManager->reconnect();

				// max(1, …) guarantees the backoff grows even when --reconnect-base-delay=0
				// (otherwise 0*2 stays 0 → a zero-sleep hot reconnect loop while down).
				$currentDelay = min(max(1, $currentDelay * 2), self::RECONNECT_MAX_DELAY);
			}
		}
	}


	protected function getNumberOfMessages(InputInterface $input): ?int
	{
		/** @var string|int $messages */
		$messages = $input->getOption('messages');

		return $messages ? (int) $messages : null;
	}


	protected function getSecondsToRun(InputInterface $input): ?int
	{
		/** @var string|int $seconds */
		$seconds = $input->getOption('time');

		return $seconds ? (int) $seconds : null;
	}


	protected function getConnectionName(InputInterface $input): ?string
	{
		/** @var string|null $connection */
		$connection = $input->getOption('connection');

		return $connection !== null && $connection !== '' ? (string) $connection : null;
	}


	private function getMaxReconnectAttempts(InputInterface $input): int
	{
		/** @var string|int $attempts */
		$attempts = $input->getOption('max-reconnect-attempts');

		return max(0, (int) $attempts);
	}


	private function getReconnectBaseDelay(InputInterface $input): int
	{
		/** @var string|int $delay */
		$delay = $input->getOption('reconnect-base-delay');
		$parsed = (int) $delay;

		return $parsed >= 0 ? $parsed : self::RECONNECT_BASE_DELAY_DEFAULT;
	}


	/**
	 * Whether a throwable caught around the consume/run() loop is a recoverable transport-layer
	 * error (the AMQP connection broke) rather than a genuine error raised by the consumer's own
	 * message handler.
	 *
	 * A handler exception originates in application code and must propagate so it is not silently
	 * retried forever; a transport error originates inside the bunny/bunny client and means the
	 * dead connection should be dropped and reconnected. The latter is recognised by its type
	 * (Bunny\Exception\ClientException — "broken pipe"/CONNECTION_FORCED), its origin (thrown from
	 * within the bunny/bunny package — e.g. the plain \LogicException Bunny raises when the broker
	 * closes the connection while a channel is still open), or its message as a final fallback.
	 *
	 * Note: the connection state cannot be used to discriminate here — Bunny leaves the client in
	 * the DISCONNECTING state when it throws "All channels have to be closed by now.", and
	 * isConnected() still reports true for that state.
	 */
	private function isReconnectableTransportError(\Throwable $exception): bool
	{
		if ($exception instanceof ClientException) {
			return true;
		}

		if (strpos($exception->getFile(), 'bunny/bunny') !== false) {
			return true;
		}

		return in_array($exception->getMessage(), self::BUNNY_TRANSPORT_ERROR_MESSAGES, true);
	}


	/**
	 * @return array{BunnyManager, AbstractConsumer}|null
	 */
	private function resolveConsumer(string $consumerName, ?string $connectionName): ?array
	{
		if ($connectionName !== null) {
			$bunnyManager = $this->connectionRegistry->get($connectionName);
			$consumer = $bunnyManager->getConsumerByAlias($consumerName);

			return $consumer !== null ? [$bunnyManager, $consumer] : null;
		}

		foreach ($this->connectionRegistry->all() as $bunnyManager) {
			$consumer = $bunnyManager->getConsumerByAlias($consumerName);
			if ($consumer !== null) {
				return [$bunnyManager, $consumer];
			}
		}

		return null;
	}

}
