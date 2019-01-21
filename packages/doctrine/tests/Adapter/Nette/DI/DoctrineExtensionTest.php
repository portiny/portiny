<?php declare(strict_types=1);

namespace Portiny\Doctrine\Tests\Adapter\Nette\DI;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Portiny\Doctrine\Tests\AbstractContainerTestCase;
use Portiny\Doctrine\Tests\Source\CastFunction;
use Portiny\Doctrine\Tests\Source\DateTimeImmutableType;
use Portiny\Doctrine\Tests\Source\IntervalType;

class DoctrineExtensionTest extends AbstractContainerTestCase
{
	public function testLoadConfiguration(): void
	{
		/** @var EntityManager $entityManager */
		$entityManager = $this->container->getByType(EntityManager::class);
		self::assertInstanceOf(EntityManager::class, $entityManager);

		/** @var EventManager $eventManager */
		$eventManager = $this->container->getByType(EventManager::class);
		self::assertInstanceOf(EventManager::class, $eventManager);
	}

	public function testBeforeCompile(): void
	{
		self::assertArrayHasKey('interval', Type::getTypesMap());
		self::assertInstanceOf(IntervalType::class, Type::getType('interval'));

		self::assertArrayHasKey('datetime', Type::getTypesMap());
		self::assertInstanceOf(DateTimeImmutableType::class, Type::getType('datetime'));

		/** @var EntityManager $entityManager */
		$entityManager = $this->container->getByType(EntityManager::class);
		$configuration = $entityManager->getConfiguration();
		self::assertNull($configuration->getCustomStringFunction('nonExistsFunctionName'));
		self::assertSame(CastFunction::class, $configuration->getCustomStringFunction('cast'));
	}
}
