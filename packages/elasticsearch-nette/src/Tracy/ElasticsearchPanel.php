<?php declare(strict_types = 1);

namespace Portiny\ElasticsearchNette\Tracy;

use Elastica\Client;
use Elastica\Request;
use Elastica\Response;
use Psr\Log\LoggerInterface;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\IBarPanel;

final class ElasticsearchPanel implements IBarPanel, LoggerInterface
{
	public const DATA_REQUEST_INDEX = 0;

	public const DATA_TIME_INDEX = 1;

	public const DATA_PATH_INDEX = 2;

	public const DATA_TRACE_INDEX = 3;

	public const DATA_METHOD_INDEX = 4;

	/**
	 * @var float
	 */
	private $totalTime = 0.0;

	/**
	 * @var array
	 */
	private $queries = [];

	/**
	 * @var Client
	 */
	private $client;


	public function __construct(Client $client)
	{
		$this->client = $client;
	}


	/**
	 * {@inheritdoc}
	 */
	public function getTab(): string
	{
		return '<span title="Elasticsearch">'
			. '<img style="padding-right: 2px;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/'
			. '9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4AUCDgIqW2pwhwAAAw1JREFUOE9tk11sk3UU'
			. 'xn//96PtC202xtwCc06Mc5PN8LFIpFnc/AjRLIQYF4M3i3qhS0xMQMONH9HEqDeSSLKQEOMlGqMgaAyiBnAzSmUEBqOgsLoO2maDru'
			. '3WtX3f/d/jhdsA8Xd1kpNznifPyUFEWEREDBGxAHxdWukXr7ztZQ5MeMl92pv8Lu672VdFl4LcglosfN+vc2Njz8ic22g1rfiVcGyX'
			. 'l9jdrQvDgIFCYayIErhv15dmTffLSqk8gLWgHCn9OPpR+ci5F3U6R+S1bmR6D37hjCgzfFMkH8MbH3gOw/lJRD5VSokBIK7X4A4nnn'
			. 'RjY+iJLEbYR+eHwXSWhv/1a+HnTyHFeA8QXnKgAvbyqp51TsAXSicTGKsnkbSHMkPcjkLmc4ieWw1YIoIxdWN242dfnIo98eEPte8Y'
			. 'NoM7esCdQmFwJ4Jy7sV12hpyJd0CKPXLybHTL7z+1YZg0MLXPvmZMl8P9BK1+5nLXULEA2WBaMxIGxdDr/DGt+d5rPVBdm7tfdw6G8'
			. '9sUApMQ+G6Pl0da0ghHK19n47IIDUzQ5SyQ4Tqn+bY3DbeOnCGePIiv/95lmjL2r1WU0N12nX1KhF4avP9dG5v5aBOkUpUaI08Smf4'
			. 'EZ69u5/95wp8cOQEmWyGkB2gjMvlTKrZ2tjesPP5bes+L0yX2dzXyv78ODm3gkJxPn+deMHgdKWOFi1cSY9T5SxDFvKItqwdVCKigO'
			. '1HM1f37f5rJFycn+f220FZawbWRxn67Rg/j45w4VqSd3v7in1dW9otpZSISCyWnUznXbfZNv4vfQiaZRrXHOK9jk7a6vsnQuY9XUqp'
			. 'v62Fvhux7WzQNPFv+Q0AAYKmRViSJKcvkJyO42vn+sONL+VEZOnY1zbV1B1fFXLuWFDRWtZX16L1LMvslTxw1xbqI+2HgFmlFEpEUE'
			. 'ohIvXHp1KHP740smmyUsJA4YlPe1UNO5of+qYpOLvH8ytbDWX+sTxQe9ixq4s3bS6oiogz41U+OXg1Udx7eVS+T43fyFbKb4qIzX9Y'
			. 'nPkHxAJ5Y3iFu8EAAAAASUVORK5CYII=" />'
			. count($this->queries) . ' queries'
			. ($this->totalTime ? ' / ' . sprintf('%0.1f', $this->totalTime * 1000) . 'ms' : '')
			. '</span>';
	}


	/**
	 * {@inheritdoc}
	 */
	public function getPanel(): string
	{
		$s = '';
		foreach ($this->queries as $query) {
			$s .= '<tr>';

			$s .= '<td>' . sprintf('%0.3f', $query[self::DATA_TIME_INDEX] * 1000) . '</td>';

			$s .= '<td class="nette-ElasticsearchPanel-request" style="min-width: 400px">' .
				Dumper::toHtml($query[self::DATA_REQUEST_INDEX], [Dumper::DEPTH => 6])
				. '</td>';

			$s .= '<td>' .
				$query[self::DATA_METHOD_INDEX] . ' ' . $query[self::DATA_PATH_INDEX] . '<br>' .
				($query[self::DATA_REQUEST_INDEX] ? json_encode($query[self::DATA_REQUEST_INDEX]) : '')
				. '</td>';

			$s .= '<td>' . Dumper::toHtml($query[self::DATA_TRACE_INDEX], [Dumper::COLLAPSE => 1]) . '</td>';

			$s .= '</tr>';
		}

		return $this->renderStyles() .
			'<h1>Queries: ' . count($this->queries) .
			($this->totalTime ? ', time: ' . sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : '') .
			'</h1>
			<div class="tracy-inner nette-ElasticsearchPanel">
				<h2>Queries</h2>
				<table>
					<tr><th>Time&nbsp;ms</th><th>Request</th><th>JSON Request</th><th>Trace</th></tr>'
					. $s .
				'</table>
			</div>';
	}


	public function bindToBar(): void
	{
		$this->client->setLogger($this);
		Debugger::getBar()->addPanel($this);
	}


	/**
	 * {@inheritdoc}
	 */
	public function debug($message, array $context = []): void
	{
		if (! isset($context['request'], $context['response'], $context['responseStatus'])) {
			return;
		}

		$this->logRequest($this->client->getLastRequest(), $this->client->getLastResponse());
	}


	/**
	 * {@inheritdoc}
	 */
	public function emergency($message, array $context = []): void
	{
	}


	/**
	 * {@inheritdoc}
	 */
	public function alert($message, array $context = []): void
	{
	}


	/**
	 * {@inheritdoc}
	 */
	public function critical($message, array $context = []): void
	{
	}


	/**
	 * {@inheritdoc}
	 */
	public function error($message, array $context = []): void
	{
	}


	/**
	 * {@inheritdoc}
	 */
	public function warning($message, array $context = []): void
	{
	}


	/**
	 * {@inheritdoc}
	 */
	public function notice($message, array $context = []): void
	{
	}


	/**
	 * {@inheritdoc}
	 */
	public function info($message, array $context = []): void
	{
	}


	/**
	 * {@inheritdoc}
	 */
	public function log($level, $message, array $context = []): void
	{
	}


	private function renderStyles(): string
	{
		return '<style>
			#tracy-debug td.nette-ElasticsearchPanel-request { background: white !important; }
			#tracy-debug td.nette-ElasticsearchPanel-request pre.tracy-dump { background: white !important; }
			#tracy-debug div.tracy-inner.nette-ElasticsearchPanel { max-width: 1000px }
			#tracy-debug .nette-ElasticsearchPanel h2 { font-size: 23px; }
			#tracy-debug .nette-ElasticsearchPanel tr table { margin: 8px 0; max-height: 150px; overflow:auto }
			</style>';
	}


	private function logRequest(?Request $request, ?Response $response): void
	{
		if (! $request || ! $response) {
			return;
		}

		$this->totalTime += $response->getQueryTime();

		$this->queries[] = [
			self::DATA_REQUEST_INDEX => $request->getData(),
			self::DATA_PATH_INDEX => $request->getPath(),
			self::DATA_TIME_INDEX => $response->getQueryTime(),
			self::DATA_TRACE_INDEX => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
			self::DATA_METHOD_INDEX => $request->getMethod(),
		];
	}

}
