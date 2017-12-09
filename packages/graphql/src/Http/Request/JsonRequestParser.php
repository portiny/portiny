<?php

declare(strict_types = 1);

namespace Portiny\GraphQL\Http\Request;

use Nette\Http\IRequest;
use Nette\Utils\Json;
use Portiny\GraphQL\Contract\Http\Request\RequestParserInterface;


final class JsonRequestParser implements RequestParserInterface
{

	/**
	 * @var array
	 */
	private $data;


	public function __construct(IRequest $httpRequest)
	{
		$rawData = $httpRequest->getRawBody();
		$this->data = Json::decode($rawData ?: '{}', Json::FORCE_ARRAY);
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
