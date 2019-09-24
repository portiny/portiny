<?php declare(strict_types = 1);

namespace Portiny\ElasticsearchNette\Tests\DI;

use Elastica\Client;
use Portiny\ElasticsearchNette\Tests\AbstractContainerTestCase;

class ElasticsearchExtensionTest extends AbstractContainerTestCase
{

	public function testLoadConfiguration(): void
	{
		$client = $this->container->getByType(Client::class);
		self::assertInstanceOf(Client::class, $client);
	}


	public function testClientSetup(): void
	{
		/** @var Client $client */
		$client = $this->container->getByType(Client::class);

		self::assertSame(
			[
				1 => [
					'host' => 'localhost',
					'port' => 9201,
				],
				[
					'host' => '12.34.56.78',
					'port' => 1234,
				],
			],
			$client->getConfig('connections')
		);
		self::assertSame('some-path', $client->getConfig('path'));
		self::assertSame('http://some.url', $client->getConfig('url'));
		self::assertSame('66.96.200.39:80', $client->getConfig('proxy'));
		self::assertSame('Http', $client->getConfig('transport'));
		self::assertTrue((bool) $client->getConfig('persistent'));
		self::assertSame(5, $client->getConfig('timeout'));
		self::assertFalse((bool) $client->getConfig('roundRobin'));
		self::assertFalse((bool) $client->getConfig('log'));
		self::assertSame(2, $client->getConfig('retryOnConflict'));
		self::assertFalse((bool) $client->getConfig('bigintConversion'));
		self::assertSame('someUserName', $client->getConfig('username'));
		self::assertSame('somePassword', $client->getConfig('password'));
	}

}
