<?php

declare(strict_types=1);

namespace Portiny\Doctrine\Adapter\Nette\DI;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Statement;
use Nette\PhpGenerator\ClassType;
use Nette\Utils\AssertionException;
use Nette\Utils\Validators;
use Portiny\Doctrine\Adapter\Nette\Tracy\DoctrineSQLPanel;
use Portiny\Doctrine\Contract\Provider\ClassMappingProviderInterface;
use Portiny\Doctrine\Contract\Provider\EntitySourceProviderInterface;
use Tracy\IBarPanel;

class DoctrineExtension extends CompilerExtension
{
	/**
	 * @var string
	 */
	public const DOCTRINE_SQL_PANEL = DoctrineSQLPanel::class;

	/**
	 * @var array
	 */
	public static $defaults = [
		'debug' => TRUE,
		'dbal' => [
			'type_overrides' => [],
			'types' => [],
			'schema_filter' => NULL,
		],
		'prefix' => 'doctrine.default',
		'proxyDir' => '%tempDir%/cache/proxies',
		'sourceDir' => NULL,
		'targetEntityMappings' => [],
		'metadata' => [],
		'functions' => [],
	];

	private $entitySources = [];

	private $classMappings = [];

	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		$config = $this->parseConfig();

		$builder = $this->getContainerBuilder();
		$name = $config['prefix'];

		$builder->addDefinition($name . '.config')
			->setType(Configuration::class)
			->addSetup(
				new Statement('setFilterSchemaAssetsExpression', [$config['dbal']['schema_filter']])
			);

		$builder->addDefinition($name . '.connection')
			->setType(Connection::class)
			->setFactory('@' . $name . '.entityManager::getConnection');

		$builder->addDefinition($name . '.entityManager')
			->setType(EntityManager::class)
			->setFactory(
				'\Doctrine\ORM\EntityManager::create',
				[
					$config['connection'],
					'@' . $name . '.config',
					'@Doctrine\Common\EventManager',
				]
			);

		$builder->addDefinition($name . '.namingStrategy')
			->setType(UnderscoreNamingStrategy::class);

		$builder->addDefinition($name . '.resolver')
			->setType(ResolveTargetEntityListener::class);

		if ($this->hasIBarPanelInterface()) {
			$builder->addDefinition($this->prefix($name . '.diagnosticsPanel'))
				->setType(self::DOCTRINE_SQL_PANEL);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile(): void
	{
		$config = $this->getConfig(self::$defaults);
		$name = $config['prefix'];

		$builder = $this->getContainerBuilder();
		$cache = $this->getCache($name, $builder);

		$configDefinition = $builder->getDefinition($name . '.config')
			->setFactory(
				'\Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration',
				[
					array_values($this->entitySources),
					$config['debug'],
					$config['proxyDir'],
					$cache,
					FALSE,
				]
			)
			->addSetup('setNamingStrategy', ['@' . $name . '.namingStrategy']);

		foreach ($config['functions'] as $functionName => $function) {
			$configDefinition->addSetup('addCustomStringFunction', [$functionName, $function]);
		}

		foreach ($this->classMappings as $source => $target) {
			$builder->getDefinition($name . '.resolver')
				->addSetup('addResolveTargetEntity', [$source, $target, []]);
		}

		if ($this->hasEventManager($builder)) {
			$builder->getDefinition($builder->getByType(EventManager::class))
				->addSetup('addEventListener', [Events::loadClassMetadata, '@' . $name . '.resolver']);
		} else {
			$builder->addDefinition($name . '.eventManager')
				->setType(EventManager::class)
				->addSetup('addEventListener', [Events::loadClassMetadata, '@' . $name . '.resolver']);
		}

		$this->processDbalTypes($name, $config['dbal']['types']);
		$this->processDbalTypeOverrides($name, $config['dbal']['type_overrides']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function afterCompile(ClassType $classType): void
	{
		$initialize = $classType->methods['initialize'];
		if ($this->hasIBarPanelInterface()) {
			$initialize->addBody('$this->getByType(\'' . self::DOCTRINE_SQL_PANEL . '\')->bindToBar();');
		}
	}

	/**
	 * @throws AssertionException
	 */
	protected function parseConfig(): array
	{
		$config = $this->getConfig(self::$defaults);
		$this->classMappings = $config['targetEntityMappings'];
		$this->entitySources = $config['metadata'];

		foreach ($this->compiler->getExtensions() as $extension) {
			if ($extension instanceof ClassMappingProviderInterface) {
				$entityMapping = $extension->getClassMapping();
				Validators::assert($entityMapping, 'array');
				$this->classMappings = array_merge($this->classMappings, $entityMapping);
			}

			if ($extension instanceof EntitySourceProviderInterface) {
				$entitySource = $extension->getEntitySource();
				Validators::assert($entitySource, 'array');
				$this->entitySources = array_merge($this->entitySources, $entitySource);
			}
		}

		if ($config['sourceDir']) {
			$this->entitySources[] = $config['sourceDir'];
		}

		return $config;
	}

	private function getCache(string $prefix, ContainerBuilder $containerBuilder): string
	{
		$cacheServiceName = $containerBuilder->getByType(Cache::class);
		if ($cacheServiceName !== NULL && strlen($cacheServiceName) > 0) {
			return '@' . $cacheServiceName;
		}

		$containerBuilder->addDefinition($prefix . '.cache')
			->setType(ArrayCache::class);

		return '@' . $prefix . '.cache';
	}

	private function hasIBarPanelInterface(): bool
	{
		return interface_exists(IBarPanel::class);
	}

	private function hasEventManager(ContainerBuilder $containerBuilder): bool
	{
		$eventManagerServiceName = $containerBuilder->getByType(EventManager::class);
		return $eventManagerServiceName !== NULL && strlen($eventManagerServiceName) > 0;
	}

	private function processDbalTypes(string $name, array $types): void
	{
		$builder = $this->getContainerBuilder();
		$entityManagerDefinition = $builder->getDefinition($name . '.entityManager');

		foreach ($types as $type => $className) {
			$entityManagerDefinition->addSetup(
				'if ( ! Doctrine\DBAL\Types\Type::hasType(?)) { Doctrine\DBAL\Types\Type::addType(?, ?); }',
				[$type, $type, $className]
			);
		}
	}

	private function processDbalTypeOverrides(string $name, array $types): void
	{
		$builder = $this->getContainerBuilder();
		$entityManagerDefinition = $builder->getDefinition($name . '.entityManager');

		foreach ($types as $type => $className) {
			$entityManagerDefinition->addSetup('Doctrine\DBAL\Types\Type::overrideType(?, ?);', [$type, $className]);
		}
	}
}
