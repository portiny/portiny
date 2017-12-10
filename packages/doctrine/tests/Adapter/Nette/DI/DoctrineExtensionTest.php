<?php declare(strict_types=1);

namespace Portiny\Doctrine\Tests\Adapter\Nette\DI;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Portiny\Doctrine\Tests\AbstractContainerTestCase;
use Portiny\Doctrine\Tests\Source\DateTimeImmutableType;
use Portiny\Doctrine\Tests\Source\IntervalType;


class DoctrineExtensionTest extends AbstractContainerTestCase
{
	public function testLoadConfiguration(): void
	{
		/** @var EntityManager $entityManager */
		$entityManager = $this->container->getByType(EntityManager::class);
		$this->assertInstanceOf(EntityManager::class, $entityManager);

		/** @var EventManager $eventManager */
		$eventManager = $this->container->getByType(EventManager::class);
		$this->assertInstanceOf(EventManager::class, $eventManager);
	}


	public function testBeforeCompile()
	{
		$this->assertArrayHasKey('interval', Type::getTypesMap());
		$this->assertInstanceOf(IntervalType::class, Type::getType('interval'));

		$this->assertArrayHasKey('datetime', Type::getTypesMap());
		$this->assertInstanceOf(DateTimeImmutableType::class, Type::getType('datetime'));
	}

}
