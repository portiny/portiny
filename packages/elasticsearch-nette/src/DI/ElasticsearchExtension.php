<?php declare(strict_types=1);

namespace Portiny\ElasticsearchNette\DI;

use Elastica\Client;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Portiny\ElasticsearchNette\Tracy\ElasticsearchPanel;

class ElasticsearchExtension extends CompilerExtension
{
	/**
	 * @var string
	 */
	public const ELASTICSEARCH_PANEL = ElasticsearchPanel::class;

	/**
	 * @var array
	 */
	private $defaults = [
		'debug' => '%debugMode%',
		'clientClassName' => Client::class,
		'connections' => [
			[
				'host' => 'localhost',
				'port' => 9200,
			],
		],
		'path' => null,
		'url' => null,
		'proxy' => null,
		'transport' => null,
		'persistent' => true,
		'timeout' => null,
		'roundRobin' => false,
		'log' => false,
		'retryOnConflict' => 0,
		'bigintConversion' => false,
		'username' => null,
		'password' => null,
	];

	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		$config = $this->validateConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		if (count($config['connections']) > 1) {
			unset($config['connections'][0]);
		}

		$builder->addDefinition($this->prefix('client'))
			->setType($config['clientClassName'])
			->setArguments([[
				'connections' => $config['connections'],
				'path' => $config['path'],
				'url' => $config['url'],
				'proxy' => $config['proxy'],
				'transport' => $config['transport'],
				'persistent' => $config['persistent'],
				'timeout' => $config['timeout'],
				'roundRobin' => $config['roundRobin'],
				'log' => $config['log'],
				'retryOnConflict' => $config['retryOnConflict'],
				'bigintConversion' => $config['bigintConversion'],
				'username' => $config['username'],
				'password' => $config['password'],
			], null, null])
			->setAutowired(true);

		if ($config['debug'] === true) {
			$builder->addDefinition($this->prefix('diagnosticsPanel'))
				->setType(self::ELASTICSEARCH_PANEL);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function afterCompile(ClassType $classType): void
	{
		$config = $this->validateConfig($this->defaults);

		if ($config['debug'] === true) {
			$initialize = $classType->methods['initialize'];
			$initialize->addBody('$this->getByType(\'' . self::ELASTICSEARCH_PANEL . '\')->bindToBar();');
		}
	}
}
