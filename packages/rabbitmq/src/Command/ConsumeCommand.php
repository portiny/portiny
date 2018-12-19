<?php declare(strict_types=1);

namespace Portiny\RabbitMQ\Command;

use Bunny\Channel;
use Nette\DI\Container;
use Portiny\RabbitMQ\BunnyManager;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsumeCommand extends Command
{
	/**
	 * @var BunnyManager
	 */
	private $bunnyManager;

	/**
	 * @var Container
	 */
	private $container;

	public function __construct(BunnyManager $bunnyManager, Container $container)
	{
		parent::__construct();

		$this->bunnyManager = $bunnyManager;
		$this->container = $container;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function configure(): void
	{
		$this->setName('rabbitmq:consume')
			->setDescription('Run a RabbitMQ consumer')
			->addArgument('consumer', InputArgument::REQUIRED, 'FQDN or alias of the consumer')
			->addOption('messages', 'm', InputArgument::OPTIONAL, 'Amount of messages to consume')
			->addOption('time', 't', InputArgument::OPTIONAL, 'Max seconds for consumer to run');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output): void
	{
		/** @var string $consumer */
		$consumer = $input->getArgument('consumer');
		$numberOfMessages = $this->getNumberOfMessages($input);
		$secondsToRun = $this->getSecondsToRun($input);

		$consumerClassName = $this->bunnyManager->getClassNameByAlias($consumer) ?: $consumer;

		/** @var Channel $channel */
		$channel = $this->bunnyManager->getChannel();

		$output->writeln('<info>Consuming...</info>');

		/** @var AbstractConsumer $consumer */
		$consumer = $this->container->getByType($consumerClassName);
		$consumer->consume($channel, $numberOfMessages);

		$this->bunnyManager->getClient()->run($secondsToRun);
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
