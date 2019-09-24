<?php declare(strict_types = 1);

namespace Portiny\GraphQLNette\Tests;

use Nette\DI\Container;
use PHPUnit\Framework\TestCase;

abstract class AbstractContainerTestCase extends TestCase
{
	/**
	 * @var Container
	 */
	protected $container;


	/**
	 * {@inheritdoc}
	 */
	protected function setUp(): void
	{
		$this->container = ContainerFactory::create();
	}

}
