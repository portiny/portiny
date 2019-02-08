# Information

This extension provide integration of [Doctrine ORM](http://www.doctrine-project.org/) into Nette Framework.


## Installation

The simplest way to install Portiny/Doctrine is using  [Composer](http://getcomposer.org/):

```sh
$ composer require portiny/doctrine
```

Enable the extension at your neon config file.

```yml
extensions:
    doctrine: Portiny\Doctrine\Adapter\Nette\DI\DoctrineExtension
```

## Minimal configuration

This extension can be configured by `doctrine` section. The minimal configuration might look like this:

```yml
doctrine:
    connection:
        driver: pdo_mysql
        host: localhost
        port: 3306
        user: username
        password: password
        dbname: database
```


## Full configuration

Via configuration you can add your own dbal types, schema filters or functions.

```yml
doctrine:
    connection:
        driver: pdo_mysql
        host: localhost
        port: 3306
        user: username
        password: password
        dbname: database

    debug: %debugMode%
    prefix: doctrine.default
    proxyDir: %tempDir%/proxies
    proxyNamespace: DoctrineProxies
    sourceDir: %appDir%/Entity
    
    entityManagerClassName: \Doctrine\ORM\EntityManager
    defaultRepositoryClassName: \Doctrine\ORM\EntityRepository
    repositoryFactory: \App\Doctrine\Repository\MyLazyRepositoryFactory
    namingStrategy: \Doctrine\ORM\Mapping\UnderscoreNamingStrategy
    sqlLogger: \App\Logger\FileLogger

    dbal:
        types:
            dateinterval: \App\Doctrine\MySQL\Types\DateIntervalType
        type_overrides:
            date: \App\Doctrine\MySQL\Types\DateTimeImmutableType
        schema_filter: "~^(?!hidden_)~" # tables and sequences that start with hidden_ are ingored by Doctrine

    functions:
        CAST: \App\Doctrine\MySQL\Functions\Cast
    
    metadataCache: default   # options: FALSE, default (Nette Storage), redis
    queryCache: default      # options: FALSE, default (Nette Storage), redis
    resultCache: default     # options: FALSE, default (Nette Storage), redis
    hydrationCache: default  # options: FALSE, default (Nette Storage), redis
    secondLevelCache:
        enabled: FALSE
        driver: default      # options: FALSE, default (Nette Storage), redis
        factoryClass: \Doctrine\ORM\Cache\DefaultCacheFactory::class
        regions:
            defaultLifetime: 3600
            defaultLockLifetime: 60
        fileLockRegionDirectory: %tempDir%/cache/Doctrine.Cache.Locks
        logging: %debugMode%
    
    cache:
        redis: # optional configuration for redis cache
            host: 127.0.0.1
            port: 6379
            timeout: 0
            reserved: null
            retryInterval: 5
```

### Modular entities

You can set entity source via own module Nette extension witch will be implements `Portiny\Doctrine\Contract\Provider\EntitySourceProviderInterface`.

```php
<?php

declare(strict_types=1);

namespace App\Modules\BlogModule\DI;

use Nette\DI\CompilerExtension;
use Portiny\Doctrine\Contract\Provider\EntitySourceProviderInterface;

final class BlogModuleExtension extends CompilerExtension implements EntitySourceProviderInterface
{
    // some module configurations ...

    /**
     * {@inheritdoc}
     */
    public function getEntitySource(): array
    {
        return [__DIR__ . '/../Entity/'];
    }
}
```

### Entity mapping

You can se mapping between entity and FQDN at entity annotations. Implement `Portiny\Doctrine\Contract\Provider\ClassMappingProviderInterface` at your Nette extension like this

```php
<?php

declare(strict_types=1);

namespace App\Modules\BlogModule\DI;

use Nette\DI\CompilerExtension;
use Portiny\Doctrine\Contract\Provider\ClassMappingProviderInterface;

final class BlogModuleExtension extends CompilerExtension implements ClassMappingProviderInterface
{
    // some module configurations ...

    /**
     * {@inheritdoc}
     */
    public function getClassMapping(): array
    {
        return ['App\\Contract\\Entity\\BlogInterface' => 'App\\Entity\\Blog'];
    }
}
```

then you can use `BlogInterface` in entity annotations e.g.

```php
/**
 * @ORM\OneToMany(targetEntity="App/Contract/Entity/BlogInterface", mappedBy="author", cascade={"persist"})
 * @var App/Contract/Entity/BlogInterface[]
 */
private $blogs;
```
