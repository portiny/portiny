<?php declare(strict_types = 1);

namespace Portiny\ElasticsearchNette\Tests\Tracy;

use Elastica\Client;
use Portiny\ElasticsearchNette\Tests\AbstractContainerTestCase;
use Portiny\ElasticsearchNette\Tracy\ElasticsearchPanel;

class ElasticserachPanelTest extends AbstractContainerTestCase
{
	/**
	 * @var ElasticsearchPanel
	 */
	private $elasticsearchPanel;


	protected function setUp(): void
	{
		parent::setUp();

		/** @var Client $client */
		$client = $this->container->getByType(Client::class);
		$this->elasticsearchPanel = new ElasticsearchPanel($client);
	}


	public function testGetTab(): void
	{
		self::assertStringContainsString('<span title="Elasticsearch">', $this->elasticsearchPanel->getTab());
		self::assertStringContainsString('0 queries', $this->elasticsearchPanel->getTab());

		// TODO: test send request to mockup client
	}


	public function testGetPanel(): void
	{
		self::assertStringContainsString('<h2>Queries</h2>', $this->elasticsearchPanel->getPanel());

		// TODO: test send request to mockup client
	}

}
