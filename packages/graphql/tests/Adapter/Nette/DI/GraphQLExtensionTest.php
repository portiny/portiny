<?php declare(strict_types=1);

namespace Portiny\GraphQL\Tests\Adapter\Nette\DI;

use Portiny\GraphQL\GraphQL\RequestProcessor;
use Portiny\GraphQL\Tests\AbstractContainerTestCase;

final class GraphQLExtensionTest extends AbstractContainerTestCase
{
	public function testLoadConfiguration(): void
	{
		$graphQLProcessor = $this->container->getByType(RequestProcessor::class);
		self::assertInstanceOf(RequestProcessor::class, $graphQLProcessor);
	}
}
