<?php

declare(strict_types=1);

namespace Portiny\Console\Http;

use Nette\Http\Request as HttpRequest;
use Nette\Http\RequestFactory;
use Nette\Http\UrlScript;

class HttpRequestFactory extends RequestFactory
{

	/**
	 * @var UrlScript
	 */
	private $urlScript;

	/**
	 * @param UrlScript|string $url
	 */
	public function setRequestUrl($url)
	{
		$this->urlScript = $url ? new UrlScript($url) : NULL;
	}

	/**
	 * @return HttpRequest
	 */
	public function createHttpRequest()
	{
		if ($this->urlScript === NULL || php_sapi_name() !== 'cli') {
			return parent::createHttpRequest();
		}

		return new HttpRequest($this->urlScript, NULL, [], [], [], [], PHP_SAPI, '127.0.0.1', '127.0.0.1');
	}

}
