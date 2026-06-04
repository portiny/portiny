<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Producer;

use Bunny\Channel;
use Portiny\RabbitMQ\ConnectionRegistry;
use React\Promise\PromiseInterface;

final class Producer
{
	/**
	 * @var ConnectionRegistry
	 */
	private $connectionRegistry;


	public function __construct(ConnectionRegistry $connectionRegistry)
	{
		$this->connectionRegistry = $connectionRegistry;
	}


	/**
	 * @param mixed $body
	 * @return bool|int|PromiseInterface
	 */
	public function produce(AbstractProducer $abstractProducer, $body)
	{
		$channel = $this->connectionRegistry->get($abstractProducer->getConnectionName())->getChannel();

		if ($channel instanceof PromiseInterface) {
			return $channel->then(function (Channel $channel) use ($abstractProducer, $body) {
				return $abstractProducer->produce($channel, $body);
			});
		}

		return $abstractProducer->produce($channel, $body);
	}

}
