<?php declare(strict_types = 1);

namespace Portiny\ElasticsearchNette\DI;

use Elastica\Client;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Portiny\ElasticsearchNette\Tracy\ElasticsearchPanel;
use stdClass;

class ElasticsearchExtension extends CompilerExtension
{
	public const ELASTICSEARCH_PANEL = ElasticsearchPanel::class;


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'debug' => Expect::bool(false),
			'clientClassName' => Expect::string(Client::class),
			'connections' => Expect::array()->default([
				[
					'host' => 'localhost',
					'port' => 9200,
				],
			]),
			'path' => Expect::string()->nullable(),
			'url' => Expect::string()->nullable(),
			'proxy' => Expect::string()->nullable(),
			'transport' => Expect::string()->nullable(),
			'persistent' => Expect::bool(true),
			'timeout' => Expect::int()->nullable(),
			'roundRobin' => Expect::bool(false),
			'log' => Expect::bool(true),
			'retryOnConflict' => Expect::int(0),
			'bigintConversion' => Expect::bool(false),
			'username' => Expect::string()->nullable(),
			'password' => Expect::string()->nullable(),
		]);
	}


	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		/** @var stdClass $config */
		$config = (object) $this->config;
		$builder = $this->getContainerBuilder();

		if (count($config->connections) > 1) {
			unset($config->connections[0]);
		}

		$builder->addDefinition($this->prefix('client'))
			->setType($config->clientClassName)
			->setArguments([[
				'connections' => $config->connections,
				'path' => $config->path,
				'url' => $config->url,
				'proxy' => $config->proxy,
				'transport' => $config->transport,
				'persistent' => $config->persistent,
				'timeout' => $config->timeout,
				'roundRobin' => $config->roundRobin,
				'log' => $config->log,
				'retryOnConflict' => $config->retryOnConflict,
				'bigintConversion' => $config->bigintConversion,
				'username' => $config->username,
				'password' => $config->password,
			], null, null])
			->setAutowired(true);

		if ($config->debug === true) {
			$builder->addDefinition($this->prefix('diagnosticsPanel'))
				->setType(self::ELASTICSEARCH_PANEL);
		}
	}


	/**
	 * {@inheritdoc}
	 */
	public function afterCompile(ClassType $classType): void
	{
		/** @var stdClass $config */
		$config = (object) $this->config;
		if ($config->debug === true) {
			$initialize = $classType->methods['initialize'];
			$initialize->addBody('$this->getByType(\'' . self::ELASTICSEARCH_PANEL . '\')->bindToBar();');
		}
	}

}
