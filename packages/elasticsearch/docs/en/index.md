# Information

This extension provide integration of [Ruflin/Elastica](https://github.com/ruflin/Elastica) into Nette Framework.


## Installation

The simplest way to install Portiny/Elasticsearch is using  [Composer](http://getcomposer.org/):

```sh
$ composer require portiny/elasticsearch
```

Enable the extension at your neon config file.

```yml
extensions:
    elasticsearch: Portiny\Elasticsearch\Adapter\Nette\DI\ElasticsearchExtension
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

Simply add `Elastica\Client` dependency into your service and use it! :)

### Simple example

```php
<?php declare(strict_types=1);

use Elastica\Client;
use Elastica\Query;
use Elastica\Query\Term;


class MyProductService
{

	/**
	 * @var Client
	 */
	private $client;


	public function __construct(Client $client)
	{
		$this->client = $client;
	}


	public function getProductsFromElastic(int $categoryId)
	{
		$index = $this->client->getIndex('products');

		$term = new Term(['categoryId' => ['value' => $categoryId]]);
		$query = new Query($term);

		$resultSet = $index->search($query);

		print_r($resultSet->getTotalHits());
		print_r($resultSet->getResults());
		// etc.
	}

}
```

More examples and Elastica documentation can be found at [elastica.io](http://elastica.io/) (very outdated but official source) or [Ruflin/Elastica](https://github.com/ruflin/Elastica) (official Github project page).
