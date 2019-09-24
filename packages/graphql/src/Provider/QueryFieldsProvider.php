<?php declare(strict_types = 1);

namespace Portiny\GraphQL\Provider;

use Portiny\GraphQL\Contract\Field\QueryFieldInterface;
use Portiny\GraphQL\Contract\Provider\QueryFieldsProviderInterface;
use Portiny\GraphQL\Converter\QueryFieldConverter;
use Portiny\GraphQL\Exception\Provider\ExistingQueryFieldException;

final class QueryFieldsProvider implements QueryFieldsProviderInterface
{
	/**
	 * @var QueryFieldInterface[]
	 */
	private $fields = [];


	public function __construct(iterable $queryFields = [])
	{
		foreach ($queryFields as $queryField) {
			$this->addField($queryField);
		}
	}


	/**
	 * {@inheritdoc}
	 */
	public function addField(QueryFieldInterface $queryField): void
	{
		$this->ensureFieldIsNotRegistered($queryField);

		$this->fields[$queryField->getName()] = $queryField;
	}


	/**
	 * {@inheritdoc}
	 */
	public function getFields(): array
	{
		return $this->fields;
	}


	public function getField(string $name): ?QueryFieldInterface
	{
		return $this->fields[$name] ?? null;
	}


	/**
	 * {@inheritdoc}
	 */
	public function convertFieldsToArray(?array $allowedQueries = null): array
	{
		$fields = [];
		foreach ($this->getFields() as $field) {
			if (is_array($allowedQueries) && ! in_array(get_class($field), $allowedQueries, true)) {
				continue;
			}

			$fields += QueryFieldConverter::toArray($field);
		}

		return $fields;
	}


	/**
	 * @throws ExistingQueryFieldException
	 */
	private function ensureFieldIsNotRegistered(QueryFieldInterface $queryField): void
	{
		if (isset($this->fields[$queryField->getName()])) {
			throw new ExistingQueryFieldException(
				sprintf('Query field with name "%s" is already registered.', $queryField->getName())
			);
		}
	}

}
