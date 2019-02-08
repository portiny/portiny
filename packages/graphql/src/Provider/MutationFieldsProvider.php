<?php

declare(strict_types=1);

namespace Portiny\GraphQL\Provider;

use Portiny\GraphQL\Contract\Mutation\MutationFieldInterface;
use Portiny\GraphQL\Contract\Provider\MutationFieldsProviderInterface;
use Portiny\GraphQL\Converter\MutationFieldConverter;
use Portiny\GraphQL\Exception\Provider\ExistingMutationFieldException;

final class MutationFieldsProvider implements MutationFieldsProviderInterface
{
	/**
	 * @var MutationFieldInterface[]
	 */
	private $fields = [];

	/**
	 * {@inheritdoc}
	 */
	public function addField(MutationFieldInterface $mutationField): void
	{
		$this->ensureFieldIsNotRegistered($mutationField);

		$this->fields[$mutationField->getName()] = $mutationField;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFields(): array
	{
		return $this->fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function convertFieldsToArray(?array $allowedMutations = null): array
	{
		$fields = [];
		foreach ($this->getFields() as $field) {
			if (is_array($allowedMutations) && ! in_array(get_class($field), $allowedMutations, true)) {
				continue;
			}

			$fields += MutationFieldConverter::toArray($field);
		}

		return $fields;
	}

	/**
	 * @throws ExistingMutationFieldException
	 */
	private function ensureFieldIsNotRegistered(MutationFieldInterface $mutationField): void
	{
		if (isset($this->fields[$mutationField->getName()])) {
			throw new ExistingMutationFieldException(
				sprintf('Mutation field with name "%s" is already registered.', $mutationField->getName())
			);
		}
	}
}
