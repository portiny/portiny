# Information

This extension provide integration of [Portiny/Elasticsearch](https://github.com/portiny/elasticsearch/) into Nette Framework.


## Installation

The simplest way to install Portiny/Elasticsearch-Nette is using  [Composer](http://getcomposer.org/):

```sh
$ composer require portiny/elasticsearch-nette
```

Enable the extension at your neon config file.

```yml
extensions:
    elasticsearch: \Portiny\ElasticsearchNette\DI\ElasticsearchExtension
```

**This extension use default Elasticsearch host and port** `(localhost:9200)` configuration. If your Elasticsearch run on a another host or multiple hosts (like cluster) follow next part called *"Simple configuration"*.

## Simple configuration

This extension can be configured by `elasticsearch` section. The basic configuration might look like this:

```yml
elasticsearch:
    connections:
        {{host: localhost, port: 9201}, {host: 12.34.56.78, port: 1234}} # multiple hosts
```


## Full configuration

Via configuration you can change all [Elastica client behaviours](https://github.com/ruflin/Elastica/blob/master/lib/Elastica/Client.php#L31).

```yml
elasticsearch:
    connections:
        {{host: localhost, port: 9201}, {host: 12.34.56.78, port: 1234}} # multiple hosts
    debug: %debugMode%
    clientClassName: App\MyApp\Elasticsearch\MyClient # must extends from \Elastica\Client
    path: null
    url: null
    proxy: null
    transport: null
    persistent: true
    timeout: null
    roundRobin: false
    log: false
    retryOnConflict: 0
    bigintConversion: false
    username: null
    password: null
```

## Usage

See official [Portiny/Elasticsearch documentation](https://github.com/portiny/elasticsearch/blob/master/docs/en/index.md#usage).
