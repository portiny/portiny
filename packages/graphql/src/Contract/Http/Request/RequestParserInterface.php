<?php

declare(strict_types=1);

namespace Portiny\GraphQL\Contract\Http\Request;

interface RequestParserInterface
{
	public function getQuery(): string;

	public function getVariables(): array;
}
