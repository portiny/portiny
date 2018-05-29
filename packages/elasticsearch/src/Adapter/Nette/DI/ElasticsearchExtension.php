<?php declare(strict_types=1);

namespace Portiny\Elasticsearch\Adapter\Nette\DI;

use Elastica\Client;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Portiny\Elasticsearch\Adapter\Nette\Tracy\ElasticsearchPanel;
use Tracy\IBarPanel;

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
			], NULL, NULL])
			->setAutowired(TRUE);

		if ($this->hasIBarPanelInterface()) {
			$builder->addDefinition($this->prefix('diagnosticsPanel'))
				->setType(self::ELASTICSEARCH_PANEL);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function afterCompile(ClassType $classType): void
	{
		$initialize = $classType->methods['initialize'];
		if ($this->hasIBarPanelInterface()) {
			$initialize->addBody('$this->getByType(\'' . self::ELASTICSEARCH_PANEL . '\')->bindToBar();');
		}
	}

	private function hasIBarPanelInterface(): bool
	{
		return interface_exists(IBarPanel::class);
	}
}
