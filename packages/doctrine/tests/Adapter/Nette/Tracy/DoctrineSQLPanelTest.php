<?php

namespace Portiny\Doctrine\Tests\Adapter\Nette\Tracy;

use Doctrine\ORM\EntityManager;
use Portiny\Doctrine\Adapter\Nette\Tracy\DoctrineSQLPanel;
use Portiny\Doctrine\Tests\AbstractContainerTestCase;
use Tracy\Debugger;


class DoctrineSQLPanelTest extends AbstractContainerTestCase
{

	/**
	 * @var DoctrineSQLPanel
	 */
	private $doctrineSQLPanel;

	protected function setUp(): void
	{
		parent::setUp();

		$this->doctrineSQLPanel = new DoctrineSQLPanel($this->container->getByType(EntityManager::class));
	}


	public function testStartQuery()
	{
		$this->doctrineSQLPanel->startQuery('SELECT 1 FROM dual', NULL, NULL);
		$this->doctrineSQLPanel->stopQuery();

		$queries = $this->doctrineSQLPanel->getQueries();
		$this->assertCount(1, $queries);
		$this->assertSame('SELECT 1 FROM dual', reset($queries)[0]);
	}


	public function testStopQuery()
	{
		$this->doctrineSQLPanel->startQuery('SELECT 1 FROM dual', NULL, NULL);
		sleep(1);
		$this->doctrineSQLPanel->stopQuery();

		$queries = $this->doctrineSQLPanel->getQueries();
		$firstQuery = reset($queries);

		$this->assertCount(1, $queries);
		$this->assertSame('SELECT 1 FROM dual', $firstQuery[0]);
		$this->assertTrue($firstQuery[3] > 0);
	}


	public function testGetTab()
	{
		$this->assertContains('<span title="Doctrine 2">', $this->doctrineSQLPanel->getTab());
		$this->assertContains('0 queries', $this->doctrineSQLPanel->getTab());

		$this->doctrineSQLPanel->startQuery('SELECT 1 FROM dual', NULL, NULL);
		$this->doctrineSQLPanel->stopQuery();

		$this->assertContains('1 queries', $this->doctrineSQLPanel->getTab());
	}


	public function testGetPanel()
	{
		$this->assertEmpty($this->doctrineSQLPanel->getPanel());

		$this->doctrineSQLPanel->startQuery('SELECT 1 FROM dual', NULL, NULL);
		$this->doctrineSQLPanel->stopQuery();

		$this->assertContains('<h1>Queries: 1, time:', $this->doctrineSQLPanel->getPanel());
	}

}
