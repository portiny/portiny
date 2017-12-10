<?php

declare(strict_types=1);

namespace Portiny\Doctrine\Adapter\Nette\Tracy;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\Cache\Logging\CacheLoggerChain;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;
use Doctrine\ORM\EntityManager;
use Nette\InvalidStateException;
use Nette\Utils\Strings;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\IBarPanel;

/**
 * Debug panel for Doctrine
 *
 * @author David Grudl
 * @author Patrik Votoček
 * @author Jan Mareš
 * @author Tomáš Pilař
 */
final class DoctrineSQLPanel implements IBarPanel, SQLLogger
{
	/**
	 * @var int
	 */
	public const DATA_INDEX_SQL = 0;

	/**
	 * @var int
	 */
	public const DATA_INDEX_PARAMS = 1;

	/**
	 * @var int
	 */
	public const DATA_INDEX_TYPES = 2;

	/**
	 * @var int
	 */
	public const DATA_INDEX_TIME = 3;

	/**
	 * @var int
	 */
	public const DATA_INDEX_EXPLAIN = 4;

	/**
	 * @var int
	 */
	public const DATA_INDEX_TRACE = 5;

	/**
	 * @var int
	 */
	public const DATA_INDEX_COUNT = 6;

	/**
	 * @var bool
	 */
	private $sortQueries = FALSE;

	/**
	 * @var bool
	 */
	private $groupQueries = FALSE;

	/**
	 * whether to do explain queries for selects or not
	 * @var bool
	 */
	private $doExplains = FALSE;

	/**
	 * @var bool
	 */
	private $explainRunning = FALSE;

	/**
	 * @var Connection|null
	 */
	private $connection;

	/**
	 * @var int
	 */
	private $totalTime = 0;

	/**
	 * @var array
	 */
	private $queries = [];

	/**
	 * @var EntityManager
	 */
	private $entityManager;

	private $queryCounter = 0;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	/**
	 * {@inheritdoc}
	 */
	public function startQuery($sql, ?array $params = NULL, ?array $types = NULL): void
	{
		if ($this->explainRunning) {
			return;
		}

		Debugger::timer('doctrine');

		$this->queries[] = [
			self::DATA_INDEX_SQL => $sql,
			self::DATA_INDEX_PARAMS => $params,
			self::DATA_INDEX_TYPES => $types,
			self::DATA_INDEX_TIME => 0,
			self::DATA_INDEX_EXPLAIN => NULL,
			self::DATA_INDEX_TRACE => debug_backtrace(PHP_VERSION_ID >= 50306 ? DEBUG_BACKTRACE_IGNORE_ARGS : FALSE),
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function stopQuery(): void
	{
		if ($this->explainRunning) {
			return;
		}

		$keys = array_keys($this->queries);
		$key = end($keys);
		$this->queries[$key][self::DATA_INDEX_TIME] = Debugger::timer('doctrine');
		$this->totalTime += $this->queries[$key][self::DATA_INDEX_TIME];

		// get EXPLAIN for SELECT queries
		if ($this->doExplains) {
			if ($this->connection === NULL) {
				throw new InvalidStateException(
					'You must set a Doctrine\DBAL\Connection to get EXPLAIN.'
				);
			}

			$query = $this->queries[$key][self::DATA_INDEX_SQL];

			if (! Strings::startsWith($query, 'SELECT')) { // only SELECTs are supported
				return;
			}

			// prevent logging explains & infinite recursion
			$this->explainRunning = TRUE;

			$params = $this->queries[$key][self::DATA_INDEX_PARAMS];
			$types = $this->queries[$key][self::DATA_INDEX_TYPES];

			if ($this->connection) {
				$stmt = $this->connection->executeQuery('EXPLAIN ' . $query, $params, $types);
				$this->queries[$key][self::DATA_INDEX_EXPLAIN] = $stmt->fetchAll();
			}

			$this->explainRunning = FALSE;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTab()
	{
		return '<span title="Doctrine 2">'
			. '<img  src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAoLQ9TAAAKT2lDQ1BQaG90b3Nob3A'
			. 'gSUNDIHByb2ZpbGUAAHjanVNnVFPpFj333vRCS4iAlEtvUhUIIFJCi4AUkSYqIQkQSoghodkVUcERRUUEG8igiAOOjoCMFVEsDIo'
			. 'K2AfkIaKOg6OIisr74Xuja9a89+bN/rXXPues852zzwfACAyWSDNRNYAMqUIeEeCDx8TG4eQuQIEKJHAAEAizZCFz/SMBAPh+PDw'
			. 'rIsAHvgABeNMLCADATZvAMByH/w/qQplcAYCEAcB0kThLCIAUAEB6jkKmAEBGAYCdmCZTAKAEAGDLY2LjAFAtAGAnf+bTAICd+Jl'
			. '7AQBblCEVAaCRACATZYhEAGg7AKzPVopFAFgwABRmS8Q5ANgtADBJV2ZIALC3AMDOEAuyAAgMADBRiIUpAAR7AGDIIyN4AISZABR'
			. 'G8lc88SuuEOcqAAB4mbI8uSQ5RYFbCC1xB1dXLh4ozkkXKxQ2YQJhmkAuwnmZGTKBNA/g88wAAKCRFRHgg/P9eM4Ors7ONo62Dl8'
			. 't6r8G/yJiYuP+5c+rcEAAAOF0ftH+LC+zGoA7BoBt/qIl7gRoXgugdfeLZrIPQLUAoOnaV/Nw+H48PEWhkLnZ2eXk5NhKxEJbYcp'
			. 'Xff5nwl/AV/1s+X48/Pf14L7iJIEyXYFHBPjgwsz0TKUcz5IJhGLc5o9H/LcL//wd0yLESWK5WCoU41EScY5EmozzMqUiiUKSKcU'
			. 'l0v9k4t8s+wM+3zUAsGo+AXuRLahdYwP2SycQWHTA4vcAAPK7b8HUKAgDgGiD4c93/+8//UegJQCAZkmScQAAXkQkLlTKsz/HCAA'
			. 'ARKCBKrBBG/TBGCzABhzBBdzBC/xgNoRCJMTCQhBCCmSAHHJgKayCQiiGzbAdKmAv1EAdNMBRaIaTcA4uwlW4Dj1wD/phCJ7BKLy'
			. 'BCQRByAgTYSHaiAFiilgjjggXmYX4IcFIBBKLJCDJiBRRIkuRNUgxUopUIFVIHfI9cgI5h1xGupE7yAAygvyGvEcxlIGyUT3UDLV'
			. 'Duag3GoRGogvQZHQxmo8WoJvQcrQaPYw2oefQq2gP2o8+Q8cwwOgYBzPEbDAuxsNCsTgsCZNjy7EirAyrxhqwVqwDu4n1Y8+xdwQ'
			. 'SgUXACTYEd0IgYR5BSFhMWE7YSKggHCQ0EdoJNwkDhFHCJyKTqEu0JroR+cQYYjIxh1hILCPWEo8TLxB7iEPENyQSiUMyJ7mQAkm'
			. 'xpFTSEtJG0m5SI+ksqZs0SBojk8naZGuyBzmULCAryIXkneTD5DPkG+Qh8lsKnWJAcaT4U+IoUspqShnlEOU05QZlmDJBVaOaUt2'
			. 'ooVQRNY9aQq2htlKvUYeoEzR1mjnNgxZJS6WtopXTGmgXaPdpr+h0uhHdlR5Ol9BX0svpR+iX6AP0dwwNhhWDx4hnKBmbGAcYZxl'
			. '3GK+YTKYZ04sZx1QwNzHrmOeZD5lvVVgqtip8FZHKCpVKlSaVGyovVKmqpqreqgtV81XLVI+pXlN9rkZVM1PjqQnUlqtVqp1Q61M'
			. 'bU2epO6iHqmeob1Q/pH5Z/YkGWcNMw09DpFGgsV/jvMYgC2MZs3gsIWsNq4Z1gTXEJrHN2Xx2KruY/R27iz2qqaE5QzNKM1ezUvO'
			. 'UZj8H45hx+Jx0TgnnKKeX836K3hTvKeIpG6Y0TLkxZVxrqpaXllirSKtRq0frvTau7aedpr1Fu1n7gQ5Bx0onXCdHZ4/OBZ3nU9l'
			. 'T3acKpxZNPTr1ri6qa6UbobtEd79up+6Ynr5egJ5Mb6feeb3n+hx9L/1U/W36p/VHDFgGswwkBtsMzhg8xTVxbzwdL8fb8VFDXcN'
			. 'AQ6VhlWGX4YSRudE8o9VGjUYPjGnGXOMk423GbcajJgYmISZLTepN7ppSTbmmKaY7TDtMx83MzaLN1pk1mz0x1zLnm+eb15vft2B'
			. 'aeFostqi2uGVJsuRaplnutrxuhVo5WaVYVVpds0atna0l1rutu6cRp7lOk06rntZnw7Dxtsm2qbcZsOXYBtuutm22fWFnYhdnt8W'
			. 'uw+6TvZN9un2N/T0HDYfZDqsdWh1+c7RyFDpWOt6azpzuP33F9JbpL2dYzxDP2DPjthPLKcRpnVOb00dnF2e5c4PziIuJS4LLLpc'
			. '+Lpsbxt3IveRKdPVxXeF60vWdm7Obwu2o26/uNu5p7ofcn8w0nymeWTNz0MPIQ+BR5dE/C5+VMGvfrH5PQ0+BZ7XnIy9jL5FXrde'
			. 'wt6V3qvdh7xc+9j5yn+M+4zw33jLeWV/MN8C3yLfLT8Nvnl+F30N/I/9k/3r/0QCngCUBZwOJgUGBWwL7+Hp8Ib+OPzrbZfay2e1'
			. 'BjKC5QRVBj4KtguXBrSFoyOyQrSH355jOkc5pDoVQfujW0Adh5mGLw34MJ4WHhVeGP45wiFga0TGXNXfR3ENz30T6RJZE3ptnMU8'
			. '5ry1KNSo+qi5qPNo3ujS6P8YuZlnM1VidWElsSxw5LiquNm5svt/87fOH4p3iC+N7F5gvyF1weaHOwvSFpxapLhIsOpZATIhOOJT'
			. 'wQRAqqBaMJfITdyWOCnnCHcJnIi/RNtGI2ENcKh5O8kgqTXqS7JG8NXkkxTOlLOW5hCepkLxMDUzdmzqeFpp2IG0yPTq9MYOSkZB'
			. 'xQqohTZO2Z+pn5mZ2y6xlhbL+xW6Lty8elQfJa7OQrAVZLQq2QqboVFoo1yoHsmdlV2a/zYnKOZarnivN7cyzytuQN5zvn//tEsI'
			. 'S4ZK2pYZLVy0dWOa9rGo5sjxxedsK4xUFK4ZWBqw8uIq2Km3VT6vtV5eufr0mek1rgV7ByoLBtQFr6wtVCuWFfevc1+1dT1gvWd+'
			. '1YfqGnRs+FYmKrhTbF5cVf9go3HjlG4dvyr+Z3JS0qavEuWTPZtJm6ebeLZ5bDpaql+aXDm4N2dq0Dd9WtO319kXbL5fNKNu7g7Z'
			. 'DuaO/PLi8ZafJzs07P1SkVPRU+lQ27tLdtWHX+G7R7ht7vPY07NXbW7z3/T7JvttVAVVN1WbVZftJ+7P3P66Jqun4lvttXa1ObXH'
			. 'txwPSA/0HIw6217nU1R3SPVRSj9Yr60cOxx++/p3vdy0NNg1VjZzG4iNwRHnk6fcJ3/ceDTradox7rOEH0x92HWcdL2pCmvKaRpt'
			. 'TmvtbYlu6T8w+0dbq3nr8R9sfD5w0PFl5SvNUyWna6YLTk2fyz4ydlZ19fi753GDborZ752PO32oPb++6EHTh0kX/i+c7vDvOXPK'
			. '4dPKy2+UTV7hXmq86X23qdOo8/pPTT8e7nLuarrlca7nuer21e2b36RueN87d9L158Rb/1tWeOT3dvfN6b/fF9/XfFt1+cif9zsu'
			. '72Xcn7q28T7xf9EDtQdlD3YfVP1v+3Njv3H9qwHeg89HcR/cGhYPP/pH1jw9DBY+Zj8uGDYbrnjg+OTniP3L96fynQ89kzyaeF/6'
			. 'i/suuFxYvfvjV69fO0ZjRoZfyl5O/bXyl/erA6xmv28bCxh6+yXgzMV70VvvtwXfcdx3vo98PT+R8IH8o/2j5sfVT0Kf7kxmTk/8'
			. 'EA5jz/GMzLdsAAAAJcEhZcwAALiMAAC4jAXilP3YAAAAHdElNRQffCxUPDinoxmA5AAABO1BMVEUAAADjcTnqakDwgzrxgz7ziDv'
			. '1gz72iUD5hz35iD75iT7/gAD/gED/gEf/gID/iz25VS33hDv3iUjngzf/hT7xgTz3hj31gTz8iTv4hj7tfTr5hj77ij78iT73hj3'
			. '6iD37hkD8iT35hz77hz77iT73hj36hz76hz/6hz76iD79iD/2hTz7iD75hz37iD76iD77iT75hz77iT75iD78ij/6iT/6iT74hz7'
			. '3hj37hz33hj30hD31gDb1gjj1gjn1hDr1hDv1hTz1hT31iEP1iUP2hT32hT72hj32i0b2lVX3hTr3hj33hz73k1L3mFz3mV33ml7'
			. '3m1/3nGP4hjz4hz74n2X4oGf4qXf4sYL5hz76iD781bz83cr849H96dz969/+7uT+8+v+8+3+9O7+9/H++PT//Pv//v7///+xeeL'
			. 'XAAAAO3RSTlMAAAAAAAAAAAAAAAAAAAAAAgUGBw0QEBERFBUZHR4fKSotP0RKW15lc32GlaWsydvo6err6+z2+vv8/YuXaogAAAA'
			. 'BYktHRGjLbPQiAAAAuklEQVQYGQXBhyIVABQA0FNkr6zsGTKzZT83e5Vsks39/y/oHFD7ZbC+HADMLU82qgRQaSpivsUHAGVtCxE'
			. '37aoBRRrGI+K6B1BhZGntVyHisB8wtBOX+bq7EWfdxfjUfBPx8yHf9wrxHfTF6vHvk5fMP4UfdTAafzMzM3NzqwsG4ur56fEt837'
			. 'loAk6D6Kwfp75by1moMpExEXmXcTOMPB5No7yNmJ7TA3g2+Lp/vX0VyWAUjp6W/mI/zsxJP3EcQMdAAAAAElFTkSuQmCC" />'
			. count($this->queries) . ' queries'
			. ($this->totalTime ? ' / ' . sprintf('%0.1f', $this->totalTime * 1000) . 'ms' : '')
			. '</span>';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPanel()
	{
		$s = '';

		if ($this->groupQueries) {
			$this->queries = $this->groupQueries($this->queries);
		}

		if ($this->sortQueries) {
			$this->sortQueries($this->queries, self::DATA_INDEX_TIME);
		}

		foreach ($this->queries as $query) {
			$s .= $this->processQuery($query);
		}

		return empty($this->queries) ? '' :
			$this->renderStyles() .
			'<h1>Queries: ' . count($this->queries) .
			($this->totalTime ? ', time: ' . sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : '') .
			'</h1>
			<div class="tracy-inner nette-Doctrine2Panel">
			' . $this->renderPanelCacheStatistics() . '
			<h2>Queries</h2>
			<table>
			<tr><th>Time&nbsp;ms</th><th>SQL</th><th>Params</th><th>Trace</th></tr>' . $s .
			'</table>
			</div>';
	}

	/**
	 * Binds panel to debug bar.
	 */
	public function bindToBar(): void
	{
		if (! $this->isTracyEnabled()) {
			return;
		}

		$this->entityManager->getConfiguration()->setSQLLogger($this);
		$this->connection = $this->entityManager->getConnection();
		Debugger::getBar()->addPanel($this);
	}

	/**
	 * @param bool $sortQueries
	 */
	public function setSortQueries($sortQueries): void
	{
		$this->sortQueries = $sortQueries;
	}

	/**
	 * @param bool $groupQueries
	 */
	public function setGroupQueries($groupQueries): void
	{
		$this->groupQueries = $groupQueries;
	}

	/**
	 * @return bool
	 */
	private function isTracyEnabled()
	{
		return php_sapi_name() !== 'cli' && Debugger::isEnabled() && ! Debugger::$productionMode;
	}

	/**
	 * @return string
	 */
	private function processQuery(array $query)
	{
		$s = '<tr>';
		$s .= '<td>' . sprintf('%0.3f', $query[self::DATA_INDEX_TIME] * 1000);

		if ($this->doExplains && isset($query[self::DATA_INDEX_EXPLAIN])) {
			++$this->queryCounter;
			$s .= "<br /><a href='#' class='nette-toggler' rel='#nette-Doctrine2Panel-row-" .
				$this->queryCounter . "'>" . 'explain&nbsp;&#x25ba;</a>';
		}

		if (isset($query[self::DATA_INDEX_COUNT])) {
			$s .= '/' . sprintf('%d', $query[self::DATA_INDEX_COUNT]);
		}

		$s .= '</td>';

		$s .= '<td class="nette-Doctrine2Panel-sql" style="min-width: 400px">' .
			Helper::dumpSql($query[self::DATA_INDEX_SQL]);

		if ($this->doExplains && isset($query[self::DATA_INDEX_EXPLAIN])) {
			$s .= "<table id='nette-Doctrine2Panel-row-" . $this->queryCounter . "' class='nette-collapsed'><tr>";
			foreach ($query[self::DATA_INDEX_EXPLAIN][0] as $col => $foo) {
				$s .= '<th>' . htmlspecialchars($col) . '</th>';
			}

			$s .= '</tr>';
			foreach ($query[self::DATA_INDEX_EXPLAIN] as $row) {
				$s .= '<tr>';
				foreach ($row as $col) {
					$s .= '<td>' . htmlspecialchars($col) . '</td>';
				}

				$s .= '</tr>';
			}

			$s .= '</table>';
		}

		$s .= '</td>';
		$s .= '<td>' . Dumper::toHtml($query[self::DATA_INDEX_PARAMS]) . '</td>';
		$s .= '<td>' . Dumper::toHtml($query[self::DATA_INDEX_TRACE]) . '</td>';
		$s .= '</tr>';

		return $s;
	}

	private function renderStyles()
	{
		return '<style>
			#tracy-debug td.nette-Doctrine2Panel-sql { background: white !important }
			#tracy-debug .nette-Doctrine2Panel-source { color: #BBB !important }
			#tracy-debug div.tracy-inner.nette-Doctrine2Panel { max-width: 1000px }
			#tracy-debug .nette-Doctrine2Panel tr table { margin: 8px 0; max-height: 150px; overflow:auto }
			#tracy-debug .nette-Doctrine2Panel-cache-green { color: green !important; font-weight: bold }
			#tracy-debug .nette-Doctrine2Panel-cache-red { color: red !important; font-weight: bold }
			#tracy-debug .nette-Doctrine2Panel h2 { font-size: 23px; }
			</style>';
	}

	private function renderPanelCacheStatistics()
	{
		if (empty($this->entityManager)) {
			return '';
		}

		$config = $this->entityManager->getConfiguration();
		if (! $config->isSecondLevelCacheEnabled()) {
			return '';
		}

		$cacheLogger = $config->getSecondLevelCacheConfiguration()->getCacheLogger();

		if (! $cacheLogger instanceof CacheLoggerChain) {
			return '';
		}

		/** @var StatisticsCacheLogger $statistics */
		$statistics = $cacheLogger->getLogger('statistics');
		if (! ($statistics)) {
			return '';
		}

		$cacheDriver = $this->entityManager->getConfiguration()->getMetadataCacheImpl();
		$driverInformation = get_class($cacheDriver);

		return '<h2>Second Level Cache</h2>
			<table>
				<tr>
					<td>Driver</td>
					<td><strong>' . $driverInformation . '</strong></td>
				</tr>
				<tr>
					<td>Cache hits</td>
					<td>
						<strong class="nette-Doctrine2Panel-cache-green">' . $statistics->getHitCount() . '</strong>
					</td>
				</tr>
				<tr>
					<td>Cache misses</td>
					<td>
						<strong class="nette-Doctrine2Panel-cache-red">' . $statistics->getMissCount() . '</strong>
					</td>
				</tr>
				<tr>
					<td>Cache puts</td>
					<td>
						<strong class="nette-Doctrine2Panel-cache-red">' . $statistics->getPutCount() . '</strong>
					</td>
				</tr>
			</table>';
	}

	/**
	 * @return array
	 */
	private function groupQueries(array $queries)
	{
		$indexed = [];
		foreach ($queries as $query) {
			$indexed[$query[self::DATA_INDEX_SQL]][] = $query;
		}

		$grouped = [];
		foreach ($indexed as $item) {
			if (count($item) === 1) {
				$grouped[] = $item[0];
				continue;
			}

			$groupedItem = $item[0];
			$times = $params = $traces = [];
			foreach ($item as $subItem) {
				$times[] = $subItem[self::DATA_INDEX_TIME];
				$params[] = $subItem[self::DATA_INDEX_PARAMS];
				$traces[] = $subItem[self::DATA_INDEX_TRACE];
			}

			$groupedItem[self::DATA_INDEX_TIME] = array_sum($times);
			$groupedItem[self::DATA_INDEX_PARAMS] = $params;
			$groupedItem[self::DATA_INDEX_TRACE] = $traces;
			$groupedItem[self::DATA_INDEX_COUNT] = count($times);
			$grouped[] = $groupedItem;
		}

		return $grouped;
	}

	private function sortQueries(array &$queries, int $key): void
	{
		uasort(
			$queries,
			function ($a, $b) use ($key) {
				if ($a[$key] === $b[$key]) {
					return 0;
				}

				return ($a[$key] > $b[$key]) ? -1 : 1;
			}
		);
	}
}
