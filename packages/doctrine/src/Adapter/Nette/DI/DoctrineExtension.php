<?php declare(strict_types = 1);

namespace Portiny\Doctrine\Adapter\Nette\DI;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\ChainCache;
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
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
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
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\AssertionException;
use Nette\Utils\Validators;
use Portiny\Doctrine\Adapter\Nette\Tracy\DoctrineSQLPanel;
use Portiny\Doctrine\Cache\DefaultCache;
use Portiny\Doctrine\Contract\Provider\ClassMappingProviderInterface;
use Portiny\Doctrine\Contract\Provider\EntitySourceProviderInterface;
use stdClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\HelperSet;

class DoctrineExtension extends CompilerExtension
{
	private const DOCTRINE_SQL_PANEL = DoctrineSQLPanel::class;

	private $classMappings = [];

	private $entitySources = [];


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'debug' => Expect::bool(false),
			'connection' => Expect::structure([
				'driver' => Expect::string('pdo_mysql'),
				'host' => Expect::string('localhost')->nullable(),
				'port' => Expect::int(3306)->nullable(),
				'user' => Expect::string('username')->nullable(),
				'password' => Expect::string('password')->nullable(),
				'dbname' => Expect::string('dbname')->nullable(),
				'memory' => Expect::bool(false)->nullable(),
			]),
			'dbal' => Expect::structure([
				'type_overrides' => Expect::array()->default([]),
				'types' => Expect::array()->default([]),
				'schema_filter' => Expect::string()->nullable(),
			]),
			'prefix' => Expect::string('doctrine.default'),
			'proxyDir' => Expect::string('%tempDir%/cache/proxies'),
			'proxyNamespace' => Expect::string('DoctrineProxies'),
			'sourceDir' => Expect::string()->nullable(),
			'entityManagerClassName' => Expect::string(EntityManager::class),
			'defaultRepositoryClassName' => Expect::string(EntityRepository::class),
			'repositoryFactory' => Expect::string()->nullable(),
			'namingStrategy' => Expect::string(UnderscoreNamingStrategy::class),
			'sqlLogger' => Expect::string()->nullable(),
			'targetEntityMappings' => Expect::array()->default([]),
			'metadata' => Expect::array()->default([]),
			'functions' => Expect::array()->default([]),
			// caches
			'metadataCache' => Expect::string('default'),
			'queryCache' => Expect::string('default'),
			'resultCache' => Expect::string('default'),
			'hydrationCache' => Expect::string('default'),
			'secondLevelCache' => Expect::structure([
				'enabled' => Expect::bool(false),
				'factoryClass' => Expect::string(DefaultCacheFactory::class),
				'driver' => Expect::string('default'),
				'regions' => Expect::structure([
					'defaultLifetime' => Expect::int(3600),
					'defaultLockLifetime' => Expect::int(60),
				]),
				'fileLockRegionDirectory' => Expect::string('%tempDir%/cache/Doctrine.Cache.Locks'),
				'logging' => Expect::bool(false),
			]),
			'cache' => Expect::structure([
				'redis' => Expect::structure([
					'class' => Expect::string(RedisCache::class),
				]),
			]),
		]);
	}


	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		$config = $this->parseConfig();

		$builder = $this->getContainerBuilder();
		$name = $config->prefix;

		$builder->addDefinition($name . '.namingStrategy')
			->setType($config->namingStrategy);

		$configurationDefinition = $builder->addDefinition($name . '.config')
			->setType(Configuration::class)
			->addSetup('setFilterSchemaAssetsExpression', [$config->dbal->schema_filter])
			->addSetup('setDefaultRepositoryClassName', [$config->defaultRepositoryClassName])
			->addSetup('setProxyDir', [$config->proxyDir])
			->addSetup('setProxyNamespace', [$config->proxyNamespace])
			->addSetup('setAutoGenerateProxyClasses', [$config->debug])
			->addSetup('setNamingStrategy', ['@' . $name . '.namingStrategy']);

		$builder->addDefinition($name . '.annotationReader')
			->setType(AnnotationReader::class)
			->setAutowired(false);

		$metadataCache = $this->getCache($name . '.metadata', $builder, $config->metadataCache ?: 'array');
		$builder->addDefinition($name . '.reader')
			->setType(Reader::class)
			->setFactory(CachedReader::class, ['@' . $name . '.annotationReader', $metadataCache, $config->debug]);

		$builder->addDefinition($name . '.annotationDriver')
			->setFactory(AnnotationDriver::class, ['@' . $name . '.reader', array_values($this->entitySources)]);

		$configurationDefinition->addSetup('setMetadataDriverImpl', ['@' . $name . '.annotationDriver']);

		foreach ($config->functions as $functionName => $function) {
			$configurationDefinition->addSetup('addCustomStringFunction', [$functionName, $function]);
		}

		if ($config->repositoryFactory) {
			$builder->addDefinition($name . '.repositoryFactory')
				->setType($config->repositoryFactory);
			$configurationDefinition->addSetup('setRepositoryFactory', ['@' . $name . '.repositoryFactory']);
		}
		if ($config->sqlLogger) {
			$builder->addDefinition($name . '.sqlLogger')
				->setType($config->sqlLogger);
			$configurationDefinition->addSetup('setSQLLogger', ['@' . $name . '.sqlLogger']);
		}

		if ($config->metadataCache !== false) {
			$configurationDefinition->addSetup(
				'setMetadataCacheImpl',
				[$this->getCache($name . '.metadata', $builder, $config->metadataCache)]
			);
		}

		if ($config->queryCache !== false) {
			$configurationDefinition->addSetup(
				'setQueryCacheImpl',
				[$this->getCache($name . '.query', $builder, $config->queryCache)]
			);
		}

		if ($config->resultCache !== false) {
			$configurationDefinition->addSetup(
				'setResultCacheImpl',
				[$this->getCache($name . '.ormResult', $builder, $config->resultCache)]
			);
		}

		if ($config->hydrationCache !== false) {
			$configurationDefinition->addSetup(
				'setHydrationCacheImpl',
				[$this->getCache($name . '.hydration', $builder, $config->hydrationCache)]
			);
		}

		$this->processSecondLevelCache($name, $config->secondLevelCache);

		$builder->addDefinition($name . '.connection')
			->setType(Connection::class)
			->setFactory('@' . $name . '.entityManager::getConnection');

		$builder->addDefinition($name . '.entityManager')
			->setType($config->entityManagerClassName)
			->setFactory(
				$config->entityManagerClassName . '::create',
				[(array) $config->connection, '@' . $name . '.config', '@Doctrine\Common\EventManager']
			);

		$builder->addDefinition($name . '.resolver')
			->setType(ResolveTargetEntityListener::class);

		if ($config->debug === true) {
			$builder->addDefinition($this->prefix($name . '.diagnosticsPanel'))
				->setType(self::DOCTRINE_SQL_PANEL);
		}

		// import Doctrine commands into Symfony/Console if exists
		$this->registerCommandsIntoConsole($builder, $name);
	}


	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile(): void
	{
		/** @var stdClass $config */
		$config = (object) $this->config;
		$name = $config->prefix;

		$builder = $this->getContainerBuilder();

		foreach ($this->classMappings as $source => $target) {
			$builder->getDefinition($name . '.resolver')
				->addSetup('addResolveTargetEntity', [$source, $target, []]);
		}

		$this->processDbalTypes($name, $config->dbal->types);
		$this->processDbalTypeOverrides($name, $config->dbal->type_overrides);
		$this->processEventSubscribers($name);
		$this->processFilters();
	}


	/**
	 * {@inheritdoc}
	 */
	public function afterCompile(ClassType $classType): void
	{
		/** @var stdClass $config */
		$config = (object) $this->config;
		$initialize = $classType->methods['initialize'];

		$initialize->addBody('?::registerUniqueLoader("class_exists");', [new PhpLiteral(AnnotationRegistry::class)]);

		if ($config->debug === true) {
			$initialize->addBody('$this->getByType(\'' . self::DOCTRINE_SQL_PANEL . '\')->bindToBar();');
		}

		$builder = $this->getContainerBuilder();
		$filterDefinitions = $builder->findByType(SQLFilter::class);
		if ($filterDefinitions !== []) {
			$initialize->addBody(
				'$filterCollection = $this->getByType(\'' . EntityManagerInterface::class . '\')->getFilters();'
			);
			foreach (array_keys($filterDefinitions) as $name) {
				$initialize->addBody('$filterCollection->enable(\'' . $name . '\');');
			}
		}
	}


	protected function processSecondLevelCache($name, stdClass $config): void
	{
		if (! $config->enabled) {
			return;
		}

		$builder = $this->getContainerBuilder();

		$cacheService = $this->getCache($name . '.secondLevel', $builder, $config->driver);

		$cacheFactoryId = '@' . $name . '.cacheRegionsConfiguration';
		$builder->addDefinition($this->prefix($name . '.cacheFactory'))
			->setType(CacheFactory::class)
			->setFactory($config->factoryClass, [$this->prefix($cacheFactoryId), $cacheService])
			->addSetup('setFileLockRegionDirectory', [$config->fileLockRegionDirectory]);

		$builder->addDefinition($this->prefix($name . '.cacheRegionsConfiguration'))
			->setFactory(RegionsConfiguration::class, [
				$config->regions->defaultLifetime,
				$config->regions->defaultLockLifetime,
			]);

		$logger = $builder->addDefinition($this->prefix($name . '.cacheLogger'))
			->setType(CacheLogger::class)
			->setFactory(CacheLoggerChain::class)
			->setAutowired(false);

		if ($config->logging) {
			$logger->addSetup('setLogger', ['statistics', new Statement(StatisticsCacheLogger::class)]);
		}

		$cacheConfigName = $this->prefix($name . '.ormCacheConfiguration');
		$builder->addDefinition($cacheConfigName)
			->setType(CacheConfiguration::class)
			->addSetup('setCacheFactory', [$this->prefix('@' . $name . '.cacheFactory')])
			->addSetup('setCacheLogger', [$this->prefix('@' . $name . '.cacheLogger')])
			->setAutowired(false);

		$configuration = $builder->getDefinitionByType(Configuration::class);
		$configuration->addSetup('setSecondLevelCacheEnabled');
		$configuration->addSetup('setSecondLevelCacheConfiguration', ['@' . $cacheConfigName]);
	}


	/**
	 * @throws AssertionException
	 */
	private function parseConfig(): stdClass
	{
		/** @var stdClass $config */
		$config = (object) $this->config;

		$this->classMappings = $config->targetEntityMappings;
		$this->entitySources = $config->metadata;

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

		if ($config->sourceDir) {
			$this->entitySources[] = $config->sourceDir;
		}

		return $config;
	}


	private function getCache(string $prefix, ContainerBuilder $containerBuilder, string $cacheType): string
	{
		if ($containerBuilder->hasDefinition($prefix . '.cache')) {
			return '@' . $prefix . '.cache';
		}

		$config = $this->parseConfig();

		switch ($cacheType) {
			case 'apcu':
				$cacheClass = ApcuCache::class;
				break;

			case 'array':
				$cacheClass = ArrayCache::class;
				break;

			case 'redis':
				$cacheClass = $config->cache->redis->class;
				break;

			case 'default':
			default:
				$cacheClass = DefaultCache::class;
				break;
		}

		$containerBuilder->addDefinition($prefix . '.cache1')
			->setType(ArrayCache::class)
			->setAutowired(false);

		$mainCacheDefinition = $containerBuilder->addDefinition($prefix . '.cache2')
			->setType($cacheClass)
			->setAutowired(false);

		$containerBuilder->addDefinition($prefix . '.cache')
			->setFactory(ChainCache::class, [['@' . $prefix . '.cache1', '@' . $prefix . '.cache2']])
			->setAutowired(false);

		if ($cacheType === 'redis') {
			$redisConfig = $config->cache->redis;

			$containerBuilder->addDefinition($prefix . '.redis')
				->setType('\Redis')
				->setAutowired(false)
				->addSetup('connect', [
					$redisConfig->host ?? '127.0.0.1',
					$redisConfig->port ?? null,
					$redisConfig->timeout ?? 0.0,
					$redisConfig->reserved ?? null,
					$redisConfig->retryInterval ?? 0,
				])
				->addSetup('select', [$redisConfig->database ?? 1]);

			$mainCacheDefinition->addSetup('setRedis', ['@' . $prefix . '.redis']);
		}

		return '@' . $prefix . '.cache';
	}


	private function registerCommandsIntoConsole(ContainerBuilder $containerBuilder, string $name): void
	{
		if ($this->hasSymfonyConsole()) {
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
			if (! empty($helperSets)) {
				/** @var ServiceDefinition $helperSet */
				$helperSet = reset($helperSets);
				$helperSet->addSetup('set', [new Statement(EntityManagerHelper::class), 'em']);
			}
		}
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
			$eventManagerDefinition = $builder->getDefinition((string) $builder->getByType(EventManager::class))
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


	private function processFilters(): void
	{
		$builder = $this->getContainerBuilder();

		$configurationService = $builder->getDefinitionByType(Configuration::class);
		foreach ($builder->findByType(SQLFilter::class) as $name => $filterDefinition) {
			$configurationService->addSetup('addFilter', [$name, $filterDefinition->getType()]);
		}
	}


	private function hasSymfonyConsole(): bool
	{
		return class_exists(Application::class);
	}


	private function hasEventManager(ContainerBuilder $containerBuilder): bool
	{
		$eventManagerServiceName = $containerBuilder->getByType(EventManager::class);
		return $eventManagerServiceName !== null && strlen($eventManagerServiceName) > 0;
	}

}
