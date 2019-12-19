<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Command;

use Bunny\Channel;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsumeCommand extends Command
{

	/**
	 * @var string
	 */
	protected static $defaultName = 'rabbitmq:consume';

	/**
	 * @var BunnyManager
	 */
	private $bunnyManager;


	public function __construct(BunnyManager $bunnyManager)
	{
		parent::__construct();

		$this->bunnyManager = $bunnyManager;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function configure(): void
	{
		$this->setName(self::$defaultName)
			->setDescription('Run a RabbitMQ consumer')
			->addArgument('consumer', InputArgument::REQUIRED, 'FQDN or alias of the consumer')
			->addOption('messages', 'm', InputArgument::OPTIONAL, 'Amount of messages to consume')
			->addOption('time', 't', InputArgument::OPTIONAL, 'Max seconds for consumer to run');
	}


	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output): ?int
	{
		/** @var string $consumerName */
		$consumerName = $input->getArgument('consumer');
		$numberOfMessages = $this->getNumberOfMessages($input);
		$secondsToRun = $this->getSecondsToRun($input);

		$consumer = $this->bunnyManager->getConsumerByAlias($consumerName);
		if ($consumer === null) {
			$output->writeln('<error>Consumer not found!</error>');
			return -1;
		}

		/** @var Channel $channel */
		$channel = $this->bunnyManager->getChannel();

		$output->writeln('<info>Consuming...</info>');

		/** @var AbstractConsumer $consumer */
		$consumer->consume($channel, $numberOfMessages);

		$this->bunnyManager->getClient()->run($secondsToRun);

		return 0;
	}


	protected function getNumberOfMessages(InputInterface $input): ?int
	{
		/** @var string|int $messages */
		$messages = $input->getOption('messages');

		return $messages ? (int) $messages : null;
	}


	protected function getSecondsToRun(InputInterface $input)
	{
		/** @var string|int $seconds */
		$seconds = $input->getOption('time');

		return $seconds ? (int) $seconds : null;
	}

}
