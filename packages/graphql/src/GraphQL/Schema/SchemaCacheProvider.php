<?php
declare(strict_types=1);

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
use Nette\Utils\FileSystem;
use Portiny\GraphQL\Contract\Provider\MutationFieldsProviderInterface;
use Portiny\GraphQL\Contract\Provider\QueryFieldsProviderInterface;
use Portiny\GraphQL\GraphQL\Type\Types;

final class SchemaCacheProvider
{
	/**
	 * @var string
	 */
	private const SCHEMA_FILENAME = 'schema.php';

	/**
	 * @var string
	 */
	private const TYPES_FILENAME = 'types.php';

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

	public function isCached(): bool
	{
		return file_exists($this->getSchemaCacheFile());
	}

	public function save(Schema $schema): void
	{
		// schema cache
		$sdl = SchemaPrinter::doPrint($schema);
		$documentNode = Parser::parse($sdl);
		FileSystem::write(
			$this->getSchemaCacheFile(),
			"<?php\nreturn " . var_export(AST::toArray($documentNode), true) . ';'
		);

		// types cache
		FileSystem::write($this->getTypesCacheFile(), serialize(Types::getTypeClasses()));
	}

	public function getSchema(): Schema
	{
		if ($this->schema !== null) {
			return $this->schema;
		}

		// load types from cache
		Types::loadTypesFromClasses(unserialize(FileSystem::read($this->getTypesCacheFile())));

		// load schema from cache
		/** @var DocumentNode $document */
		$document = AST::fromArray(require $this->getSchemaCacheFile());
		$this->schema = BuildSchema::build($document, $this->getTypeConfigDecorator());

		return $this->schema;
	}

	private function getSchemaCacheFile(): string
	{
		return $this->cacheDir . '/' . self::SCHEMA_FILENAME;
	}

	private function getTypesCacheFile(): string
	{
		return $this->cacheDir . '/' . self::TYPES_FILENAME;
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
