<?php declare(strict_types=1);

namespace Portiny\Doctrine\Tests\Adapter\Nette\DI;

use Doctrine\ORM\EntityManager;
use Portiny\Doctrine\Tests\AbstractContainerTestCase;

class DoctrineExtensionTest extends AbstractContainerTestCase
{
	public function testLoadConfiguration(): void
	{
		$application = $this->container->getByType(EntityManager::class);
		$this->assertInstanceOf(EntityManager::class, $application);
	}
}
