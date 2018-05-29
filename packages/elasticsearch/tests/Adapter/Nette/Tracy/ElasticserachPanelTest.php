<?php declare(strict_types=1);

namespace Portiny\Elasticsearch\Tests\Adapter\Nette\Tracy;

use Elastica\Client;
use Portiny\Elasticsearch\Adapter\Nette\Tracy\ElasticsearchPanel;
use Portiny\Elasticsearch\Tests\AbstractContainerTestCase;

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
		$this->assertContains('<span title="Elasticsearch">', $this->elasticsearchPanel->getTab());
		$this->assertContains('0 queries', $this->elasticsearchPanel->getTab());

		// TODO: test send request to mockup client
	}

	public function testGetPanel(): void
	{
		$this->assertContains('<h2>Queries</h2>', $this->elasticsearchPanel->getPanel());

		// TODO: test send request to mockup client
	}
}
