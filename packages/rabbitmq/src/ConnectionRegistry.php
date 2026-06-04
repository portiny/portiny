<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ;

use InvalidArgumentException;

final class ConnectionRegistry
{
	/**
	 * @var array<string, BunnyManager>
	 */
	private $managers;

	/**
	 * @var string
	 */
	private $defaultConnection;


	/**
	 * @param array<string, BunnyManager> $managers
	 */
	public function __construct(array $managers, string $defaultConnection = 'default')
	{
		$this->managers = $managers;
		$this->defaultConnection = $defaultConnection;
	}


	/**
	 * Returns the manager for the given connection name. When $name is null, the default connection is used.
	 *
	 * @throws InvalidArgumentException when the requested connection does not exist
	 */
	public function get(?string $name = null): BunnyManager
	{
		$name = $name ?? $this->defaultConnection;

		if (! isset($this->managers[$name])) {
			throw new InvalidArgumentException(sprintf(
				'RabbitMQ connection "%s" does not exist. Available connections: %s.',
				$name,
				$this->managers === [] ? '(none)' : implode(', ', $this->getNames())
			));
		}

		return $this->managers[$name];
	}


	public function has(string $name): bool
	{
		return isset($this->managers[$name]);
	}


	public function getDefaultConnectionName(): string
	{
		return $this->defaultConnection;
	}


	/**
	 * @return array<int, string>
	 */
	public function getNames(): array
	{
		return array_keys($this->managers);
	}


	/**
	 * @return array<string, BunnyManager>
	 */
	public function all(): array
	{
		return $this->managers;
	}

}
