<?php declare(strict_types=1);

namespace Portiny\Console\Tests\Source;

use Nette\Http\IRequest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PrintRequestUrlCommand extends Command
{
	/**
	 * @var IRequest
	 */
	private $request;

	public function __construct(IRequest $request)
	{
		parent::__construct();

		$this->request = $request;
	}

	protected function configure(): void
	{
		$this->setName('print-request-url')
			->setDescription('Print request URL');
	}

	protected function execute(InputInterface $input, OutputInterface $output): void
	{
		$output->write($this->request->getUrl()->getAbsoluteUrl());
	}
}
