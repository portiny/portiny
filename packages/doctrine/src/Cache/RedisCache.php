<?php
declare(strict_types=1);

namespace Portiny\Doctrine\Cache;

use Doctrine\Common\Cache\RedisCache as DoctrineRedisCache;

final class RedisCache extends DoctrineRedisCache
{
	/**
	 * @var string[]
	 */
	private $prefetchCache = [];

	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id): bool
	{
		// preload value into local variable becase Doctrine need it if TRUE is returned
		$value = $this->doFetch($id);
		$this->prefetchCache[$id] = $value;

		return $value !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		// try load value from prefetch cache
		$doFetch = $this->prefetchCache[$id] ?? null;
		$this->prefetchCache = [];

		if ($doFetch === null) {
			$doFetch = parent::doFetch($id);
		}

		return $doFetch;
	}
}
