<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Command;

use Portiny\RabbitMQ\ConnectionRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'rabbitmq:declare',
	description: 'Creates all exchanges and queues.'
)]
final class DeclareCommand extends Command
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


	protected function configure(): void
	{
		$this->setName('rabbitmq:declare')
			->setDescription('Creates all exchanges and queues.')
			->addOption('connection', 'c', InputOption::VALUE_OPTIONAL, 'Name of the RabbitMQ connection');
	}


	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		/** @var string|null $connectionName */
		$connectionName = $input->getOption('connection');

		if ($connectionName !== null && $connectionName !== '') {
			try {
				$bunnyManager = $this->connectionRegistry->get($connectionName);
			} catch (\InvalidArgumentException $exception) {
				$output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
				return -1;
			}

			$output->write(sprintf('<comment>Declaring connection "%s"...</comment>', $connectionName));

			$bunnyManager->declare();

			$output->writeln(' <info>DONE</info>');

			return 0;
		}

		foreach ($this->connectionRegistry->all() as $name => $bunnyManager) {
			$output->write(sprintf('<comment>Declaring connection "%s"...</comment>', $name));

			$bunnyManager->declare();

			$output->writeln(' <info>DONE</info>');
		}

		return 0;
	}

}
