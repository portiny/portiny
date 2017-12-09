<?php

declare(strict_types=1);

namespace Portiny\GraphQL\Tests;

use Nette\Configurator;
use Nette\DI\Container;


final class ContainerFactory
{

	public static function create(): Container
	{
		$configurator = new Configurator;
		$configurator->setTempDirectory(TEMP_DIR);
		$configurator->addConfig(__DIR__ . '/config/config.neon');

		return $configurator->createContainer();
	}

}
