<?php declare(strict_types=1);

namespace Portiny\Console\Tests\Source;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SomeBasicCommand extends Command
{
	protected function configure(): void
	{
		$this->setName('some-basic')
			->setDescription('Some test command');
	}

	protected function execute(InputInterface $input, OutputInterface $output): void
	{
		$output->write('Success!');
	}
}
