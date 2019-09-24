<?php declare(strict_types = 1);

namespace Portiny\GraphQL\GraphQL\Schema;

use Closure;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use Portiny\GraphQL\Contract\Provider\MutationFieldsProviderInterface;
use Portiny\GraphQL\Contract\Provider\QueryFieldsProviderInterface;
use Portiny\GraphQL\GraphQL\Type\Types;

final class SchemaCacheProvider
{
	/**
	 * @var string
	 */
	private $cacheDir;

	/**
	 * @var QueryFieldsProviderInterface
	 */
	private $queryFieldsProvider;

	/**
	 * @var MutationFieldsProviderInterface
	 */
	private $mutationFieldsProvider;

	/**
	 * @var Schema
	 */
	private $schema;


	public function __construct(
		string $cacheDir,
		QueryFieldsProviderInterface $queryFieldsProvider,
		MutationFieldsProviderInterface $mutationFieldsProvider
	) {
		$this->cacheDir = $cacheDir;
		$this->queryFieldsProvider = $queryFieldsProvider;
		$this->mutationFieldsProvider = $mutationFieldsProvider;
	}


	public function getSchema(string $cacheKey): ?Schema
	{
		if ($this->schema !== null) {
			return $this->schema;
		}

		// load types from cache
		$cacheContent = file_get_contents($this->getTypesCacheFile($cacheKey));
		if ($cacheContent === false) {
			return null;
		}
		Types::loadTypesFromClasses(unserialize($cacheContent));

		// load schema from cache
		/** @var DocumentNode $document */
		$document = AST::fromArray(require $this->getSchemaCacheFile($cacheKey));
		$this->schema = BuildSchema::build($document, $this->getTypeConfigDecorator());

		return $this->schema;
	}


	public function isCached(string $cacheKey): bool
	{
		return file_exists($this->getSchemaCacheFile($cacheKey));
	}


	public function save(string $cacheKey, Schema $schema): void
	{
		// schema cache
		$sdl = SchemaPrinter::doPrint($schema);
		$documentNode = Parser::parse($sdl);
		file_put_contents(
			$this->getSchemaCacheFile($cacheKey),
			"<?php\nreturn " . var_export(AST::toArray($documentNode), true) . ';'
		);

		// types cache
		file_put_contents($this->getTypesCacheFile($cacheKey), serialize(Types::getTypeClasses()));
	}


	public function getCacheKey(?array $allowedQueries = null, ?array $allowedMutations = null): string
	{
		return md5(serialize($allowedQueries) . serialize($allowedMutations));
	}


	private function getTypesCacheFile(string $cacheKey): string
	{
		@mkdir($this->cacheDir, 0777, true); //@ - may exists
		return $this->cacheDir . '/types-' . $cacheKey . '.php';
	}


	private function getSchemaCacheFile(string $cacheKey): string
	{
		@mkdir($this->cacheDir, 0777, true); //@ - may exists
		return $this->cacheDir . '/schema-' . $cacheKey . '.php';
	}


	private function getTypeConfigDecorator(): Closure
	{
		return function (array $typeConfig) {
			$typeConfig['resolveField'] = function ($value, $args, $context, ResolveInfo $info) use ($typeConfig) {
				$fieldName = (string) $info->fieldName;

				switch ($typeConfig['name']) {
					case 'Query':
						$queryField = $this->queryFieldsProvider->getField($fieldName);
						if ($queryField) {
							return $queryField->resolve($value, $args, $context);
						}
						break;

					case 'Mutation':
						$mutationField = $this->mutationFieldsProvider->getField($fieldName);
						if ($mutationField) {
							return $mutationField->resolve($value, $args, $context);
						}
						break;

					default:
						$type = Types::findByName($typeConfig['name']);
						if ($type instanceof ObjectType) {
							$fieldNameForResolving = $fieldName;
							if ($type->hasField($fieldNameForResolving)) {
								$typeField = $type->getField($fieldNameForResolving);
								$resolver = $typeField->resolveFn;
								if (is_callable($resolver)) {
									return $resolver($value, $args, $context, $info);
								}
							}

							$resolver = $type->resolveFieldFn;
							if (is_callable($resolver)) {
								return $resolver($value, $args, $context, $info);
							}

							return null;
						}
				}

				return null;
			};

			return $typeConfig;
		};
	}

}
