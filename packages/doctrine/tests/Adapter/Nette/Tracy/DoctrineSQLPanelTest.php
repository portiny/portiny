<?php declare(strict_types=1);

namespace Portiny\Doctrine\Tests\Adapter\Nette\Tracy;

use Doctrine\ORM\EntityManager;
use Portiny\Doctrine\Adapter\Nette\Tracy\DoctrineSQLPanel;
use Portiny\Doctrine\Tests\AbstractContainerTestCase;

class DoctrineSQLPanelTest extends AbstractContainerTestCase
{
	/**
	 * @var DoctrineSQLPanel
	 */
	private $doctrineSQLPanel;

	protected function setUp(): void
	{
		parent::setUp();

		/** @var EntityManager $entityManager */
		$entityManager = $this->container->getByType(EntityManager::class);
		$this->doctrineSQLPanel = new DoctrineSQLPanel($entityManager);
	}

	public function testStartQuery(): void
	{
		$this->doctrineSQLPanel->startQuery('SELECT 1 FROM dual', NULL, NULL);
		$this->doctrineSQLPanel->stopQuery();

		$queries = $this->doctrineSQLPanel->getQueries();
		$this->assertCount(1, $queries);
		$this->assertSame('SELECT 1 FROM dual', reset($queries)[0]);
	}

	public function testStopQuery(): void
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

	public function testGetTab(): void
	{
		$this->assertContains('<span title="Doctrine 2">', $this->doctrineSQLPanel->getTab());
		$this->assertContains('0 queries', $this->doctrineSQLPanel->getTab());

		$this->doctrineSQLPanel->startQuery('SELECT 1 FROM dual', NULL, NULL);
		$this->doctrineSQLPanel->stopQuery();

		$this->assertContains('1 queries', $this->doctrineSQLPanel->getTab());
	}

	public function testGetPanel(): void
	{
		$this->assertContains('<h2>Queries</h2>', $this->doctrineSQLPanel->getPanel());

		$this->doctrineSQLPanel->startQuery('SELECT 1 FROM dual', NULL, NULL);
		$this->doctrineSQLPanel->stopQuery();

		$this->assertContains('<h1>Queries: 1, time:', $this->doctrineSQLPanel->getPanel());
	}
}
