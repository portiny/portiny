<?php declare(strict_types = 1);

namespace Portiny\Doctrine\Contract\Provider;

interface ClassMappingProviderInterface
{

	/**
	 * eg. ['App/Contract/Entity/PersonInterface' => 'App/Entity/Person']
	 */
	public function getClassMapping(): array;

}
