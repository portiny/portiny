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
		$this->doctrineSQLPanel->startQuery('SELECT 1 FROM dual', null, null);
		$this->doctrineSQLPanel->stopQuery();

		$queries = $this->doctrineSQLPanel->getQueries();
		self::assertCount(1, $queries);
		self::assertSame('SELECT 1 FROM dual', reset($queries)[0]);
	}

	public function testStopQuery(): void
	{
		$this->doctrineSQLPanel->startQuery('SELECT 1 FROM dual', null, null);
		sleep(1);
		$this->doctrineSQLPanel->stopQuery();

		$queries = $this->doctrineSQLPanel->getQueries();
		$firstQuery = reset($queries);

		self::assertCount(1, $queries);
		self::assertSame('SELECT 1 FROM dual', $firstQuery[0]);
		self::assertTrue($firstQuery[3] > 0);
	}

	public function testGetTab(): void
	{
		self::assertStringContainsString('<span title="Doctrine 2">', $this->doctrineSQLPanel->getTab());
		self::assertStringContainsString('0 queries', $this->doctrineSQLPanel->getTab());

		$this->doctrineSQLPanel->startQuery('SELECT 1 FROM dual', null, null);
		$this->doctrineSQLPanel->stopQuery();

		self::assertStringContainsString('1 queries', $this->doctrineSQLPanel->getTab());
	}

	public function testGetPanel(): void
	{
		self::assertStringContainsString('<h2>Queries</h2>', $this->doctrineSQLPanel->getPanel());

		$this->doctrineSQLPanel->startQuery('SELECT 1 FROM dual', null, null);
		$this->doctrineSQLPanel->stopQuery();

		self::assertStringContainsString('<h1>Queries: 1, time:', $this->doctrineSQLPanel->getPanel());
	}
}
