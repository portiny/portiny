<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Producer;

use Bunny\Channel;
use Portiny\RabbitMQ\BunnyManager;
use React\Promise\PromiseInterface;

final class Producer
{
	/**
	 * @var BunnyManager
	 */
	private $bunnyManager;


	public function __construct(BunnyManager $bunnyManager)
	{
		$this->bunnyManager = $bunnyManager;
	}


	/**
	 * @param mixed $body
	 * @return bool|int|PromiseInterface
	 */
	public function produce(AbstractProducer $abstractProducer, $body)
	{
		$channel = $this->bunnyManager->getChannel();

		if ($channel instanceof PromiseInterface) {
			return $channel->then(function (Channel $channel) use ($abstractProducer, $body) {
				return $abstractProducer->produce($channel, $body);
			});
		}

		return $abstractProducer->produce($channel, $body);
	}

}
