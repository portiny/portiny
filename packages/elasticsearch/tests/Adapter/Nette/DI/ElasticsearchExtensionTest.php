<?php declare(strict_types=1);

namespace Portiny\Elasticsearch\Tests\Adapter\Nette\DI;

use Elastica\Client;
use Portiny\Elasticsearch\Tests\AbstractContainerTestCase;

class ElasticsearchExtensionTest extends AbstractContainerTestCase
{
	public function testLoadConfiguration(): void
	{
		$client = $this->container->getByType(Client::class);
		$this->assertInstanceOf(Client::class, $client);
	}

	public function testClientSetup(): void
	{
		/** @var Client $client */
		$client = $this->container->getByType(Client::class);

		$this->assertSame(
			[1 => [
				'host' => 'localhost',
				'port' => 9201,
			], [
				'host' => '12.34.56.78',
				'port' => 1234,
			]],
			$client->getConfig('connections')
		);
		$this->assertSame('some-path', $client->getConfig('path'));
		$this->assertSame('http://some.url', $client->getConfig('url'));
		$this->assertSame('66.96.200.39:80', $client->getConfig('proxy'));
		$this->assertSame('Http', $client->getConfig('transport'));
		$this->assertTrue((bool) $client->getConfig('persistent'));
		$this->assertSame(5, $client->getConfig('timeout'));
		$this->assertFalse((bool) $client->getConfig('roundRobin'));
		$this->assertFalse((bool) $client->getConfig('log'));
		$this->assertSame(2, $client->getConfig('retryOnConflict'));
		$this->assertFalse((bool) $client->getConfig('bigintConversion'));
		$this->assertSame('someUserName', $client->getConfig('username'));
		$this->assertSame('somePassword', $client->getConfig('password'));
	}
}
