# Information

This extension provide integration of [Ruflin/Elastica](https://github.com/ruflin/Elastica) into Nette Framework.


## Installation

The simplest way to install Portiny/Elasticsearch is using  [Composer](http://getcomposer.org/):

```sh
$ composer require portiny/elasticsearch
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
