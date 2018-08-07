<?php

declare(strict_types=1);

namespace Portiny\Doctrine\Adapter\Nette\DI;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\RedisCache;
use Doctrine\Common\EventManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\Command\ImportCommand;
use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Cache\CacheFactory;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\Logging\CacheLogger;
use Doctrine\ORM\Cache\Logging\CacheLoggerChain;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\ORM\Tools\Console\Command\ClearCache\MetadataCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\ResultCommand;
use Doctrine\ORM\Tools\Console\Command\ConvertMappingCommand;
use Doctrine\ORM\Tools\Console\Command\GenerateEntitiesCommand;
use Doctrine\ORM\Tools\Console\Command\GenerateProxiesCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;
use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\ServiceDefinition;
use Nette\DI\Statement;
use Nette\PhpGenerator\ClassType;
use Nette\Utils\AssertionException;
use Nette\Utils\Validators;
use Portiny\Console\Adapter\Nette\DI\ConsoleExtension;
use Portiny\Doctrine\Adapter\Nette\Tracy\DoctrineSQLPanel;
use Portiny\Doctrine\Cache\DefaultCache;
use Portiny\Doctrine\Contract\Provider\ClassMappingProviderInterface;
use Portiny\Doctrine\Contract\Provider\EntitySourceProviderInterface;
use Redis;
use Symfony\Component\Console\Helper\HelperSet;
use Tracy\IBarPanel;

class DoctrineExtension extends CompilerExtension
{
	/**
	 * @var string
	 */
	private const DOCTRINE_SQL_PANEL = DoctrineSQLPanel::class;

	/**
	 * @var array
	 */
	private static $defaults = [
		'debug' => '%debugMode%',
		'dbal' => [
			'type_overrides' => [],
			'types' => [],
			'schema_filter' => NULL,
		],
		'prefix' => 'doctrine.default',
		'proxyDir' => '%tempDir%/cache/proxies',
		'sourceDir' => NULL,
		'entityManagerClassName' => EntityManager::class,
		'defaultRepositoryClassName' => EntityRepository::class,
		'repositoryFactory' => NULL,
		'namingStrategy' => UnderscoreNamingStrategy::class,
		'sqlLogger' => NULL,
		'targetEntityMappings' => [],
		'metadata' => [],
		'functions' => [],
		// caches
		'metadataCache' => 'default',
		'queryCache' => 'default',
		'resultCache' => 'default',
		'hydrationCache' => 'default',
		'secondLevelCache' => [
			'enabled' => FALSE,
			'factoryClass' => DefaultCacheFactory::class,
			'driver' => 'default',
			'regions' => [
				'defaultLifetime' => 3600,
				'defaultLockLifetime' => 60,
			],
			'fileLockRegionDirectory' => '%tempDir%/cache/Doctrine.Cache.Locks',
			'logging' => '%debugMode%',
		],
		'cache' => [
			'redis' => [],
		],
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

		$configurationDefinition = $builder->addDefinition($name . '.config')
			->setType(Configuration::class)
			->addSetup('setFilterSchemaAssetsExpression', [$config['dbal']['schema_filter']])
			->addSetup('setDefaultRepositoryClassName', [$config['defaultRepositoryClassName']]);

		if ($config['repositoryFactory']) {
			$builder->addDefinition($name . '.repositoryFactory')
				->setClass($config['repositoryFactory']);
			$configurationDefinition->addSetup('setRepositoryFactory', ['@' . $name . '.repositoryFactory']);
		}
		if ($config['sqlLogger']) {
			$builder->addDefinition($name . '.sqlLogger')
				->setClass($config['sqlLogger']);
			$configurationDefinition->addSetup('setSQLLogger', ['@' . $name . '.sqlLogger']);
		}

		if ($config['metadataCache'] !== FALSE) {
			$configurationDefinition->addSetup(
				'setMetadataCacheImpl',
				[$this->getCache($name . '.metadata', $builder, $config['metadataCache'])]
			);
		}

		if ($config['queryCache'] !== FALSE) {
			$configurationDefinition->addSetup(
				'setQueryCacheImpl',
				[$this->getCache($name . '.query', $builder, $config['queryCache'])]
			);
		}

		if ($config['resultCache'] !== FALSE) {
			$configurationDefinition->addSetup(
				'setResultCacheImpl',
				[$this->getCache($name . '.ormResult', $builder, $config['resultCache'])]
			);
		}

		if ($config['hydrationCache'] !== FALSE) {
			$configurationDefinition->addSetup(
				'setHydrationCacheImpl',
				[$this->getCache($name . '.hydration', $builder, $config['hydrationCache'])]
			);
		}

		$this->processSecondLevelCache($name, $config['secondLevelCache']);

		$builder->addDefinition($name . '.connection')
			->setType(Connection::class)
			->setFactory('@' . $name . '.entityManager::getConnection');

		$builder->addDefinition($name . '.entityManager')
			->setType($config['entityManagerClassName'])
			->setFactory(
				$config['entityManagerClassName'] . '::create',
				[$config['connection'], '@' . $name . '.config', '@Doctrine\Common\EventManager']
			);

		$builder->addDefinition($name . '.namingStrategy')
			->setType($config['namingStrategy']);

		$builder->addDefinition($name . '.resolver')
			->setType(ResolveTargetEntityListener::class);

		if ($this->hasIBarPanelInterface()) {
			$builder->addDefinition($this->prefix($name . '.diagnosticsPanel'))
				->setType(self::DOCTRINE_SQL_PANEL);
		}

		// import Doctrine commands into Portiny/Console if exists
		$this->registerCommandsIntoConsole($builder, $name);
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile(): void
	{
		$config = $this->getConfig(self::$defaults);
		$name = $config['prefix'];

		$builder = $this->getContainerBuilder();

		$configDefinition = $builder->getDefinition($name . '.config')
			->setFactory(
				'\Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration',
				[array_values($this->entitySources), $config['debug'], $config['proxyDir'], NULL, FALSE]
			)
			->addSetup('setNamingStrategy', ['@' . $name . '.namingStrategy']);

		foreach ($config['functions'] as $functionName => $function) {
			$configDefinition->addSetup('addCustomStringFunction', [$functionName, $function]);
		}

		foreach ($this->classMappings as $source => $target) {
			$builder->getDefinition($name . '.resolver')
				->addSetup('addResolveTargetEntity', [$source, $target, []]);
		}

		$this->processDbalTypes($name, $config['dbal']['types']);
		$this->processDbalTypeOverrides($name, $config['dbal']['type_overrides']);
		$this->processEventSubscribers($name);
		$this->processFilters($name);
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

		$initialize->addBody(
			'$filterCollection = $this->getByType(\'' . EntityManagerInterface::class . '\')->getFilters();'
		);
		$builder = $this->getContainerBuilder();
		foreach ($builder->findByType(SQLFilter::class) as $name => $filterDefinition) {
			$initialize->addBody('$filterCollection->enable(\'' . $name . '\');');
		}
	}

	protected function processSecondLevelCache($name, array $config): void
	{
		if (! $config['enabled']) {
			return;
		}

		$builder = $this->getContainerBuilder();

		$cacheService = $this->getCache($name . '.secondLevel', $builder, $config['driver']);

		$builder->addDefinition($this->prefix($name . '.cacheFactory'))
			->setType(CacheFactory::class)
			->setFactory($config['factoryClass'], [
				$this->prefix('@' . $name . '.cacheRegionsConfiguration'),
				$cacheService,
			])
			->addSetup('setFileLockRegionDirectory', [$config['fileLockRegionDirectory']]);

		$builder->addDefinition($this->prefix($name . '.cacheRegionsConfiguration'))
			->setFactory(RegionsConfiguration::class, [
				$config['regions']['defaultLifetime'],
				$config['regions']['defaultLockLifetime'],
			]);

		$logger = $builder->addDefinition($this->prefix($name . '.cacheLogger'))
			->setType(CacheLogger::class)
			->setFactory(CacheLoggerChain::class)
			->setAutowired(FALSE);

		if ($config['logging']) {
			$logger->addSetup('setLogger', ['statistics', new Statement(StatisticsCacheLogger::class)]);
		}

		$cacheConfigName = $this->prefix($name . '.ormCacheConfiguration');
		$builder->addDefinition($cacheConfigName)
			->setType(CacheConfiguration::class)
			->addSetup('setCacheFactory', [$this->prefix('@' . $name . '.cacheFactory')])
			->addSetup('setCacheLogger', [$this->prefix('@' . $name . '.cacheLogger')])
			->setAutowired(FALSE);

		$configuration = $builder->getDefinitionByType(Configuration::class);
		$configuration->addSetup('setSecondLevelCacheEnabled');
		$configuration->addSetup('setSecondLevelCacheConfiguration', ['@' . $cacheConfigName]);
	}

	private function getCache(string $prefix, ContainerBuilder $containerBuilder, string $cacheType): string
	{
		$cacheServiceName = $containerBuilder->getByType(Cache::class);
		if ($cacheServiceName !== NULL && strlen($cacheServiceName) > 0) {
			return '@' . $cacheServiceName;
		}

		$cacheClass = ArrayCache::class;
		if ($cacheType) {
			switch ($cacheType) {
				case 'redis':
					$cacheClass = RedisCache::class;
					break;

				case 'default':
				default:
					$cacheClass = DefaultCache::class;
					break;
			}
		}

		$cacheDefinition = $containerBuilder->addDefinition($prefix . '.cache')
			->setType($cacheClass);

		if ($cacheType === 'redis') {
			$config = $this->parseConfig();
			$redisConfig = $config['cache']['redis'];

			$containerBuilder->addDefinition($prefix . '.redis')
				->setType(Redis::class)
				->setAutowired(FALSE)
				->addSetup('connect', [
					$redisConfig['host'] ?? '127.0.0.1',
					$redisConfig['port'] ?? null,
					$redisConfig['timeout'] ?? 0.0,
					$redisConfig['reserved'] ?? null,
					$redisConfig['retryInterval'] ?? 0,
				])
				->addSetup('select', [$redisConfig['database'] ?? 1]);

			$cacheDefinition->addSetup('setRedis', ['@' . $prefix . '.redis']);
		}

		return '@' . $prefix . '.cache';
	}

	/**
	 * @throws AssertionException
	 */
	private function parseConfig(): array
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

	private function hasIBarPanelInterface(): bool
	{
		return interface_exists(IBarPanel::class);
	}

	private function hasPortinyConsole(): bool
	{
		return class_exists(ConsoleExtension::class);
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

	private function processEventSubscribers(string $name): void
	{
		$builder = $this->getContainerBuilder();

		if ($this->hasEventManager($builder)) {
			$eventManagerDefinition = $builder->getDefinition($builder->getByType(EventManager::class))
				->addSetup('addEventListener', [Events::loadClassMetadata, '@' . $name . '.resolver']);
		} else {
			$eventManagerDefinition = $builder->addDefinition($name . '.eventManager')
				->setType(EventManager::class)
				->addSetup('addEventListener', [Events::loadClassMetadata, '@' . $name . '.resolver']);
		}

		foreach (array_keys($builder->findByType(EventSubscriber::class)) as $serviceName) {
			$eventManagerDefinition->addSetup('addEventSubscriber', ['@' . $serviceName]);
		}
	}

	private function processFilters(string $name): void
	{
		$builder = $this->getContainerBuilder();

		$configurationService = $builder->getDefinitionByType(Configuration::class);
		foreach ($builder->findByType(SQLFilter::class) as $name => $filterDefinition) {
			$configurationService->addSetup('addFilter', [$name, $filterDefinition->getType()]);
		}
	}

	private function registerCommandsIntoConsole(ContainerBuilder $containerBuilder, string $name): void
	{
		if ($this->hasPortinyConsole()) {
			$commands = [
				ConvertMappingCommand::class,
				CreateCommand::class,
				DropCommand::class,
				GenerateEntitiesCommand::class,
				GenerateProxiesCommand::class,
				ImportCommand::class,
				MetadataCommand::class,
				QueryCommand::class,
				ResultCommand::class,
				UpdateCommand::class,
				ValidateSchemaCommand::class,
			];
			foreach ($commands as $index => $command) {
				$containerBuilder->addDefinition($name . '.command.' . $index)
					->setType($command);
			}

			$helperSets = $containerBuilder->findByType(HelperSet::class);
			if ($helperSets) {
				/** @var ServiceDefinition $helperSet */
				$helperSet = reset($helperSets);
				$helperSet->addSetup('set', [new Statement(EntityManagerHelper::class), 'em']);
			}
		}
	}
}
