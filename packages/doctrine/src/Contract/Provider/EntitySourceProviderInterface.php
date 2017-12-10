<?php

declare(strict_types=1);

namespace Portiny\Doctrine\Contract\Provider;

interface EntitySourceProviderInterface
{
	/**
	 * eg. ['App\Entity' => __DIR__ . '/../Entity']
	 * @return array
	 */
	public function getEntitySource(): array;
}
