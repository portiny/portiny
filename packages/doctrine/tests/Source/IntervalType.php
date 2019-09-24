<?php declare(strict_types = 1);

namespace Portiny\Doctrine\Tests\Source;

use DateInterval as Interval;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

class IntervalType extends Type
{
	public const NAME = 'interval';


	/**
	 * {@inheritdoc}
	 */
	public function getName()
	{
		return self::NAME;
	}


	/**
	 * {@inheritdoc}
	 */
	public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
	{
		return self::NAME;
	}


	/**
	 * {@inheritdoc}
	 */
	public function convertToDatabaseValue($value, AbstractPlatform $platform)
	{
		if ($value === null) {
			return null;
		}

		if (! $value instanceof Interval) {
			throw new InvalidArgumentException('Interval value must be instance of DateInterval');
		}

		$parts = [
			'y' => 'year',
			'm' => 'month',
			'd' => 'day',
			'h' => 'hour',
			'i' => 'minute',
			's' => 'second',
		];

		$sql = '';
		foreach ($parts as $key => $part) {
			$val = $value->{$key};
			if (empty($val)) {
				continue;
			}

			$sql .= " {$val} {$part}";
		}

		return trim($sql);
	}


	/**
	 * {@inheritdoc}
	 */
	public function convertToPHPValue($value, AbstractPlatform $platform)
	{
		if ($value === null) {
			return null;
		}

		$matches = [];
		preg_match(
			'/(?:(?P<y>[0-9]+) (?:year|years))?'
			. ' ?(?:(?P<m>[0-9]+) (?:months|month|mons|mon))?'
			. ' ?(?:(?P<d>[0-9]+) (?:days|day))?'
			. ' ?(?:(?P<h>[0-9]{2}):(?P<i>[0-9]{2}):(?P<s>[0-9]{2}))?/i',
			$value,
			$matches
		);

		if (empty($matches)) {
			throw ConversionException::conversionFailed($value, self::NAME);
		}

		$interval = new Interval('PT0S');

		if (! empty($matches['y'])) {
			$interval->y = intval($matches['y']);
		}

		if (! empty($matches['m'])) {
			$interval->m = intval($matches['m']);
		}

		if (! empty($matches['d'])) {
			$interval->d = intval($matches['d']);
		}

		if (! empty($matches['h'])) {
			$interval->h = intval($matches['h']);
		}

		if (! empty($matches['i'])) {
			$interval->i = intval($matches['i']);
		}

		if (! empty($matches['s'])) {
			$interval->s = intval($matches['s']);
		}

		return $interval;
	}

}
