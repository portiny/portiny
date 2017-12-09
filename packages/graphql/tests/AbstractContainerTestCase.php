<?php

namespace Portiny\GraphQL\Tests;

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
	protected function setUp()
	{
		$this->container = ContainerFactory::create();
	}

}
