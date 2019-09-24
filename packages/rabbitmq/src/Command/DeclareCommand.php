<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Command;

use Portiny\RabbitMQ\BunnyManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DeclareCommand extends Command
{
	/**
	 * @var BunnyManager
	 */
	private $bunnyManager;


	public function __construct(BunnyManager $bunnyManager)
	{
		parent::__construct();

		$this->bunnyManager = $bunnyManager;
	}


	protected function configure(): void
	{
		$this->setName('rabbitmq:declare')
			->setDescription('Creates all exchanges and queues.');
	}


	protected function execute(InputInterface $input, OutputInterface $output): ?int
	{
		$output->write('<comment>Declaring...</comment>');

		$this->bunnyManager->declare();

		$output->writeln(' <info>DONE</info>');

		return 0;
	}

}
