<?php declare(strict_types = 1);

namespace Portiny\Doctrine\Tests\Source;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType;

class DateTimeImmutableType extends DateTimeType
{
	public const NAME = 'datetime_immutable';


	public function getName(): string
	{
		return static::NAME;
	}


	/**
	 * @param DateTimeImmutable|string|null $value
	 * @return DateTimeImmutable|null
	 * @throws ConversionException
	 */
	public function convertToPHPValue($value, AbstractPlatform $platform)
	{
		if ($value === null || $value instanceof DateTimeImmutable) {
			return $value;
		}

		$dateTime = DateTimeImmutable::createFromFormat($platform->getDateTimeFormatString(), $value);

		if ($dateTime === false) {
			$dateTime = date_create_immutable($value);
		}

		if ($dateTime === false) {
			throw ConversionException::conversionFailedFormat(
				$value,
				$this->getName(),
				$platform->getDateTimeFormatString()
			);
		}

		return $dateTime;
	}


	/**
	 * @param DateTimeInterface|null $value
	 * @return string|null
	 * @throws ConversionException
	 */
	public function convertToDatabaseValue($value, AbstractPlatform $platform)
	{
		if ($value === null) {
			return $value;
		}

		if ($value instanceof DateTimeInterface) {
			return $value->format($platform->getDateTimeFormatString());
		}

		if (! is_scalar($value)) {
			$value = sprintf('of type %s', gettype($value));
		}

		throw ConversionException::conversionFailed($value, $this->getName());
	}


	public function requiresSQLCommentHint(AbstractPlatform $platform): bool
	{
		return true;
	}

}
