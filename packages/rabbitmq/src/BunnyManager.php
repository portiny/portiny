<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ;

use Bunny\Async\Client as AsyncClient;
use Bunny\Channel;
use Bunny\Client;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;
use Portiny\RabbitMQ\Exchange\AbstractExchange;
use Portiny\RabbitMQ\Queue\AbstractQueue;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

final class BunnyManager
{
	/**
	 * @var bool
	 */
	private $isDeclared = false;

	/**
	 * @var array
	 */
	private $connection = [];

	/**
	 * @var array
	 */
	private $aliases = [];

	/**
	 * @var iterable
	 */
	private $consumers = [];

	/**
	 * @var iterable
	 */
	private $exchanges = [];

	/**
	 * @var iterable
	 */
	private $queues = [];

	/**
	 * @var LoopInterface
	 */
	private $loop;

	/**
	 * @var Client|AsyncClient|null
	 */
	private $client;

	/**
	 * @var Channel|PromiseInterface|null
	 */
	private $channel;


	public function __construct(
		array $connection,
		array $aliases,
		iterable $consumers,
		iterable $exchanges,
		iterable $queues
	) {
		$this->connection = $connection;
		$this->aliases = $aliases;
		$this->consumers = $consumers;
		$this->exchanges = $exchanges;
		$this->queues = $queues;
	}


	public function setLoop(LoopInterface $loop): void
	{
		$this->loop = $loop;
	}


	/**
	 * @return Client|AsyncClient
	 */
	public function getClient()
	{
		if ($this->client === null) {
			if ($this->loop === null) {
				$this->client = new Client($this->connection);
			} else {
				$this->client = new AsyncClient($this->loop, $this->connection);
			}
		}

		return $this->client;
	}


    /**
     * @return array<string, mixed>
     */
    public function getConnection(): array
    {
        return $this->connection;
    }


	/**
	 * @return Channel|PromiseInterface
	 */
	public function getChannel()
	{
		if ($this->channel === null) {
			$this->channel = $this->createChannel();
		}

		return $this->channel;
	}


	public function getConsumerByAlias(string $alias): ?AbstractConsumer
	{
		$consumerClassName = $this->aliases[$alias] ?? null;
		if ($consumerClassName !== null) {
			/** @var AbstractConsumer $consumer */
			foreach ($this->consumers as $consumer) {
				if ($consumer instanceof $consumerClassName) {
					return $consumer;
				}
			}
		}

		return null;
	}


	/**
	 * @return bool|PromiseInterface
	 */
	public function declare()
	{
		if ($this->isDeclared) {
			return false;
		}

		$channel = $this->getChannel();

		if ($channel instanceof PromiseInterface) {
			$this->isDeclared = true;

			return $channel->then(function (Channel $channel) {
				$this->declareExchanges($channel);
				$this->declareQueues($channel);

				return true;
			});
		}

		$this->declareExchanges($channel);
		$this->declareQueues($channel);

		$this->isDeclared = true;

		return true;
	}


	/**
	 * @return Channel|PromiseInterface
	 */
	private function createChannel()
	{
		$client = $this->getClient();

		if (! $client->isConnected()) {
			if ($client instanceof AsyncClient) {
				return $client->connect()->then(function (AsyncClient $client) {
					return $client->channel();
				});
			}

			$client->connect();
		}

		return $client->channel();
	}


	private function declareExchanges(Channel $channel): void
	{
		/** @var AbstractExchange $exchange */
		foreach ($this->exchanges as $exchange) {
			$exchange->declare($channel);
		}

		/** @var AbstractExchange $exchange */
		foreach ($this->exchanges as $exchange) {
			$exchange->declareBindings($channel);
		}
	}


	private function declareQueues(Channel $channel): void
	{
		/** @var AbstractQueue $queue */
		foreach ($this->queues as $queue) {
			$queue->declare($channel);
		}
	}

}
