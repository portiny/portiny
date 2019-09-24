<?php declare(strict_types = 1);

namespace Portiny\Doctrine\Contract\Provider;

interface EntitySourceProviderInterface
{

	/**
	 * eg. [__DIR__ . '/../Entity']
	 */
	public function getEntitySource(): array;

}
