<?php

declare(strict_types = 1);

namespace Portiny\GraphQL\Contract\Http\Request;


interface RequestParserInterface
{

	function getQuery(): string;


	function getVariables(): array;

}
