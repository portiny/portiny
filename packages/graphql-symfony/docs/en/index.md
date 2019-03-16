# Information

This bundle provide integration of [Portiny/GraphQL](https://github.com/portiny/graphql/) into Symfony Framework.


## Installation

The simplest way to install Portiny/GraphQL-Symfony is using  [Composer](http://getcomposer.org/):

```sh
$ composer require portiny/graphql-symfony
```

Enable the bundle by adding it to the list of registered bundles in the `app/AppKernel.php` file of your project.

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new Portiny\GraphQLSymfony\PortinyGraphQLSymfonyBundle(),
        ];

        // ...
    }

    // ...
}
```

## Configuration

Enable auto-tagging for queries and mutations classes:

```yaml
services:
    instanceof:
        Portiny\GraphQL\Contract\Mutation\MutationFieldInterface:
            tags: ['portiny.graphql.mutation_field']
        Portiny\GraphQL\Contract\Field\QueryFieldInterface:
            tags: ['portiny.graphql.query_field']
```

Available configuration might look like this:

```yml
parameters:
    portiny.graphql.settings.debug: '%kernel.debug%'
    portiny.graphql.schema_cache.enabled: true # highly recommended for the production environment
    portiny.graphql.schema_cache.cache_dir: '%kernel.cache_dir%/graphql'
```


## Usage

See official [Portiny/GraphQL documentation](https://github.com/portiny/graphql/blob/master/docs/en/index.md#usage).
