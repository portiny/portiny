# Information

This extension provide integration of [Portiny/GraphQL](https://github.com/portiny/graphql/) into Nette Framework.


## Installation

The simplest way to install Portiny/GraphQL-Nette is using  [Composer](http://getcomposer.org/):

```sh
$ composer require portiny/graphql-nette
```

Enable the extension at your neon config file.

```yml
extensions:
    graphql: Portiny\GraphQLNette\DI\GraphQLExtension
```


## Configuration

This extension can be configured by `graphql` section. Available configuration might look like this:

```yml
graphql:
    debug: %debugMode%
    schemaCache:
        enabled: true # highly recommended for the production environment
        cacheDir: %tempDir%/cache/graphql
```


## Usage

See official [Portiny/GraphQL documentation](https://github.com/portiny/graphql/blob/master/docs/en/index.md#usage).
