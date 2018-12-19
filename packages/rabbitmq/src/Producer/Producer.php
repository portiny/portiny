<?php declare(strict_types=1);

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
	 * @param string $body
	 * @return bool|int|PromiseInterface
	 */
	public function produce(AbstractProducer $abstractProducer, $body)
	{
		$channel = $this->bunnyManager->getChannel();

		if ($channel instanceof PromiseInterface) {
			return $channel->then(function (Channel $channel) use ($abstractProducer, $body) {
				return $this->callPublish($channel, $body, $abstractProducer);
			});
		}

		return $this->callPublish($channel, $body, $abstractProducer);
	}

	/**
	 * @param string $body
	 * @return bool|int|PromiseInterface
	 */
	private function callPublish(Channel $channel, $body, AbstractProducer $abstractProducer)
	{
		return $channel->publish(
			$body,
			$abstractProducer->getHeaders(),
			$abstractProducer->getExchangeName(),
			$abstractProducer->getRoutingKey(),
			$abstractProducer->isMandatory(),
			$abstractProducer->isImmediate()
		);
	}
}
