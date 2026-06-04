<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Command;

use Bunny\Channel;
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
			->addOption('connection', 'c', InputOption::VALUE_OPTIONAL, 'Name of the RabbitMQ connection');
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

		/** @var Channel $channel */
		$channel = $bunnyManager->getChannel();

		$output->writeln('<info>Consuming...</info>');

		$consumer->consume($channel, $numberOfMessages);

		$bunnyManager->getClient()->run($secondsToRun);

		return 0;
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
