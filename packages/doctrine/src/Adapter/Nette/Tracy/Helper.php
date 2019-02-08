<?php declare(strict_types=1);

namespace Portiny\Doctrine\Adapter\Nette\Tracy;

use Doctrine\DBAL\Connection;
use Nette\Utils\Strings;

final class Helper
{
	/**
	 * Returns syntax highlighted SQL command.
	 */
	public static function dumpSql(string $sql, ?array $params = null, ?Connection $connection = null): string
	{
		static $keywords1 = 'SELECT|(?:ON\s+DUPLICATE\s+KEY)?UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|'
			. 'DELETE|CALL|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|SET|VALUES|LEFT\s+JOIN|'
			. 'INNER\s+JOIN|TRUNCATE';
		static $keywords2 = 'ALL|DISTINCT|DISTINCTROW|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|[RI]?LIKE|'
			. 'REGEXP|TRUE|FALSE';

		// insert new lines
		$sql = " ${sql} ";
		$sql = (string) preg_replace("#(?<=[\\s,(])(${keywords1})(?=[\\s,)])#i", "\n\$1", $sql);
		// reduce spaces
		$sql = (string) preg_replace('#[ \t]{2,}#', ' ', $sql);
		$sql = wordwrap($sql, 100);
		$sql = (string) preg_replace('#([ \t]*\r?\n){2,}#', "\n", $sql);
		// syntax highlight
		$sql = htmlspecialchars($sql, ENT_IGNORE, 'UTF-8');
		$closure = function ($matches) {
			if (! empty($matches[1])) { // comment
				return '<em style="color:gray">' . $matches[1] . '</em>';
			} elseif (! empty($matches[2])) { // error
				return '<strong style="color:red">' . $matches[2] . '</strong>';
			} elseif (! empty($matches[3])) { // most important keywords
				return '<strong style="color:blue">' . $matches[3] . '</strong>';
			} elseif (! empty($matches[4])) { // other keywords
				return '<strong style="color:green">' . $matches[4] . '</strong>';
			}
		};
		$sql = (string) preg_replace_callback(
			"#(/\\*.+?\\*/)|(\\*\\*.+?\\*\\*)|(?<=[\\s,(])(${keywords1})(?=[\\s,)])|'
			. '(?<=[\\s,(=])(${keywords2})(?=[\\s,)=])#is",
			$closure,
			$sql
		);

		// parameters
		$sql = (string) preg_replace_callback('#\?#', function () use ($params, $connection) {
			static $i = 0;
			if (! isset($params[$i])) {
				return '?';
			}

			$param = $params[$i++];
			if (is_string($param)
				&& (preg_match('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]#u', $param) || preg_last_error())
			) {
				return '<i title="Length ' . strlen($param) . ' bytes">&lt;binary&gt;</i>';
			} elseif (is_string($param)) {
				$length = Strings::length($param);
				$truncated = Strings::truncate($param, 120);
				$text = htmlspecialchars(
					$connection ? $connection->quote($truncated) : '\'' . $truncated . '\'',
					ENT_NOQUOTES,
					'UTF-8'
				);
				return '<span title="Length ' . $length . ' characters">' . $text . '</span>';
			} elseif (is_resource($param)) {
				$type = get_resource_type($param);
				if ($type === 'stream') {
					$info = stream_get_meta_data($param);
					return '<i' . (isset($info['uri']) ? ' title="' .
							htmlspecialchars($info['uri'], ENT_NOQUOTES, 'UTF-8') . '"' : null)
						. '>&lt;' . htmlspecialchars($type, ENT_NOQUOTES, 'UTF-8') . ' resource&gt;</i> ';
				}
			}

			return htmlspecialchars((string) $param, ENT_NOQUOTES, 'UTF-8');
		}, $sql);

		return '<pre class="dump">' . trim($sql) . "</pre>\n";
	}
}
