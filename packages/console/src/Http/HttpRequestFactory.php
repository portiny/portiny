<?php

declare(strict_types=1);

namespace Portiny\Console\Http;

use Nette\Http\Request as HttpRequest;
use Nette\Http\RequestFactory;
use Nette\Http\UrlScript;

class HttpRequestFactory extends RequestFactory
{
	/**
	 * @var UrlScript|null
	 */
	private $urlScript;

	/**
	 * @param UrlScript|string $url
	 */
	public function setRequestUrl($url): void
	{
		$this->urlScript = $url ? new UrlScript($url) : null;
	}

	public function createHttpRequest(): HttpRequest
	{
		if ($this->urlScript === null || PHP_SAPI !== 'cli') {
			return parent::createHttpRequest();
		}

		return new HttpRequest($this->urlScript, null, [], [], [], [], PHP_SAPI, '127.0.0.1', '127.0.0.1');
	}
}
