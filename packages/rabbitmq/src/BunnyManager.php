<?php declare(strict_types=1);

namespace Portiny\RabbitMQ;

use Bunny\Async\Client as AsyncClient;
use Bunny\Channel;
use Bunny\Client;
use Bunny\Exception\BunnyException;
use Bunny\Protocol\MethodExchangeBindOkFrame;
use Bunny\Protocol\MethodExchangeDeclareOkFrame;
use Bunny\Protocol\MethodQueueBindOkFrame;
use Bunny\Protocol\MethodQueueDeclareOkFrame;
use Nette\DI\Container;
use Portiny\RabbitMQ\Exchange\AbstractExchange;
use Portiny\RabbitMQ\Queue\AbstractQueue;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

final class BunnyManager
{
	/**
	 * @var array
	 */
	private $config = [];

	/**
	 * @var bool
	 */
	private $isDeclared = FALSE;

	/**
	 * @var Container
	 */
	private $container;

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

	public function __construct(Container $container, array $config)
	{
		$this->container = $container;
		$this->config = $config;
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
			if ($this->loop === NULL) {
				$this->client = new Client($this->config['connection']);
			} else {
				$this->client = new AsyncClient($this->loop, $this->config['connection']);
			}
		}

		return $this->client;
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

	public function getClassNameByAlias(string $alias): ?string
	{
		return $this->config['aliases'][$alias] ?? null;
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
		foreach ($this->config['exchanges'] as $exchangeClassName) {
			/** @var AbstractExchange $exchange */
			$exchange = $this->container->getByType($exchangeClassName);

			$frame = $channel->exchangeDeclare(
				$exchange->getName(),
				$exchange->getType(),
				$exchange->isPassive(),
				$exchange->isDurable(),
				$exchange->isAutoDelete(),
				$exchange->isInternal(),
				FALSE,
				$exchange->getArguments()
			);
			if (! $frame instanceof MethodExchangeDeclareOkFrame) {
				throw new BunnyException(sprintf('Could not declare exchange "%s".', $exchange->getName()));
			}
		}

		foreach ($this->config['exchanges'] as $exchangeClassName) {
			/** @var AbstractExchange $exchange */
			$exchange = $this->container->getByType($exchangeClassName);

			foreach ($exchange->getBindings() as $exchangeBind) {
				$frame = $channel->exchangeBind(
					$exchangeBind->getDestination(),
					$exchange->getName(),
					$exchangeBind->getRoutingKey(),
					FALSE,
					$exchangeBind->getArguments()
				);
				if (! $frame instanceof MethodExchangeBindOkFrame) {
					throw new BunnyException(
						sprintf(
							'Could not bind exchange "%s" to "%s" with routing key "%s".',
							$exchangeBind->getDestination(),
							$exchange->getName(),
							$exchangeBind->getRoutingKey()
						)
					);
				}
			}
		}
	}

	private function declareQueues(Channel $channel): void
	{
		foreach ($this->config['queues'] as $queueClassName) {
			/** @var AbstractQueue $queue */
			$queue = $this->container->getByType($queueClassName);

			$frame = $channel->queueDeclare(
				$queue->getName(),
				$queue->isPassive(),
				$queue->isDurable(),
				$queue->isExclusive(),
				$queue->isAutoDelete(),
				FALSE,
				$queue->getArguments()
			);
			if (! $frame instanceof MethodQueueDeclareOkFrame) {
				throw new BunnyException(sprintf('Could not declare queue "%s".', $queue->getName()));
			}

			foreach ($queue->getBindings() as $queueBind) {
				$frame = $channel->queueBind(
					$queue->getName(),
					$queueBind->getExchange(),
					$queueBind->getRoutingKey(),
					FALSE,
					$queueBind->getArguments()
				);
				if (! $frame instanceof MethodQueueBindOkFrame) {
					throw new BunnyException(
						sprintf(
							'Could not bind queue "%s" to "%s" with routing key "%s".',
							$queue->getName(),
							$queueBind->getExchange(),
							$queueBind->getRoutingKey()
						)
					);
				}
			}
		}
	}
}
