<?php declare(strict_types = 1);

namespace Portiny\Doctrine\Cache;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Utils\Strings;
use ReflectionClass;

final class DefaultCache extends CacheProvider
{
	public const CACHE_NS = 'Doctrine';

	/**
	 * @var bool
	 */
	private $debugMode = false;

	/**
	 * @var Cache
	 */
	private $cache;


	public function __construct(IStorage $storage, string $namespace = self::CACHE_NS, bool $debugMode = false)
	{
		$this->cache = new Cache($storage, $namespace);
		$this->debugMode = $debugMode;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		$cached = $this->cache->load($id);
		return $cached !== null ? $cached : false;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id)
	{
		return $this->cache->load($id) !== null;
	}


	/**
	 * @param string $id
	 * @param string|object $data
	 * @param int $lifeTime
	 * @return bool
	 */
	protected function doSave($id, $data, $lifeTime = 0)
	{
		if ($this->debugMode !== true) {
			return $this->doSaveDependingOnFiles($id, $data, [], $lifeTime);
		}

		$files = [];
		if ($data instanceof ClassMetadata) {
			$files[] = self::getClassFilename($data->name);
			foreach ($data->parentClasses as $class) {
				$files[] = self::getClassFilename($class);
			}
		}

		if (! empty($data)) {
			$m = Strings::match($id, '#(?P<class>[^@$[\].]+)(?:\$(?P<prop>[^@$[\].]+))?\@\[Annot\]#i');
			if ($m !== null && class_exists($m['class'])) {
				$files[] = self::getClassFilename($m['class']);
			}
		}

		return $this->doSaveDependingOnFiles($id, $data, $files, $lifeTime);
	}


	/**
	 * @param string $id
	 * @param mixed $data
	 * @param int $lifeTime
	 * @return bool
	 */
	protected function doSaveDependingOnFiles($id, $data, array $files, $lifeTime = 0)
	{
		$dependencies = [
			Cache::TAGS => ['doctrine'],
			Cache::FILES => $files,
		];
		if ($lifeTime > 0) {
			$dependencies[Cache::EXPIRE] = time() + $lifeTime;
		}

		$this->cache->save($id, $data, $dependencies);

		return true;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doDelete($id)
	{
		$this->cache->save($id, null);

		return true;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doFlush()
	{
		$this->cache->clean([
			Cache::TAGS => ['doctrine'],
		]);

		return true;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doGetStats()
	{
		return [
			self::STATS_HITS => null,
			self::STATS_MISSES => null,
			self::STATS_UPTIME => null,
			self::STATS_MEMORY_USAGE => null,
			self::STATS_MEMORY_AVAILABLE => null,
		];
	}


	private static function getClassFilename(string $className): string
	{
		$reflection = new ReflectionClass($className);
		return (string) $reflection->getFileName();
	}

}
