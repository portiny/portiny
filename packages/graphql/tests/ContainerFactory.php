<?php

declare(strict_types=1);

namespace Portiny\GraphQL\Tests;

use Nette\Configurator;
use Nette\DI\Container;
use Nette\Utils\FileSystem;

final class ContainerFactory
{
	public static function create(): Container
	{
		$tempDir = __DIR__ . '/temp/' . getmypid();

		if (! file_exists($tempDir . '/log')) {
			mkdir($tempDir . '/log', 0777, TRUE);
		}

		register_shutdown_function(function (): void {
			FileSystem::delete(__DIR__ . '/temp');
		});

		$configurator = new Configurator();
		$configurator->setTempDirectory($tempDir);
		$configurator->addConfig(__DIR__ . '/config/config.neon');

		return $configurator->createContainer();
	}
}
