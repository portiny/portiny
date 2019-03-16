<?php

declare(strict_types=1);

namespace Portiny\GraphQL\Http\Request;

use Nette\Utils\Json;
use Portiny\GraphQL\Contract\Http\Request\RequestParserInterface;

final class JsonRequestParser implements RequestParserInterface
{
	/**
	 * @var array
	 */
	private $data = [];

	public function __construct(string $json)
	{
		$this->data = Json::decode($json ?: '{}', Json::FORCE_ARRAY);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getQuery(): string
	{
		return $this->data['query'] ?? '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getVariables(): array
	{
		return $this->data['variables'] ?? [];
	}
}
