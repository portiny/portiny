# Information

This extension provide integration of [Bunny/Bunny](https://github.com/jakubkulhan/bunny).


## Installation

The simplest way to install Portiny/RabbitMQ is using  [Composer](http://getcomposer.org/):

```sh
$ composer require portiny/rabbitmq
```

## Usage

Usage is pretty simple. Just create your own consumers/exchanges/producers/queues classes which inherits from abstract classes from this package. Then implement required methods and register them into Nette DI container.

### Exchange example

```php
<?php declare(strict_types=1);

namespace App\Service\Exchange;

use Portiny\RabbitMQ\Exchange\AbstractExchange;

final class LogExchange extends AbstractExchange
{
    protected function getName(): string
    {
        return 'logExchange';
    }
    
    protected function getType(): string
    {
        return self::TYPE_FANOUT;
    }
}
```

### Queue with binding to exchange example

```php
<?php declare(strict_types=1);

namespace App\Service\Queue;

use Portiny\RabbitMQ\Queue\AbstractQueue;
use Portiny\RabbitMQ\Queue\QueueBind;

final class LogQueue extends AbstractQueue
{
    protected function getName(): string
    {
        return 'log';
    }

    protected function getBindings(): array
    {
        return [
            new QueueBind('logExchange')
        ];
    }
}
```

### Producer example

```php
<?php declare(strict_types=1);

namespace App\Service\Producer;

use Exception;
use Portiny\RabbitMQ\Producer\AbstractProducer;
use Portiny\RabbitMQ\Producer\Producer;

final class LogProducer extends AbstractProducer
{
    /**
     * @var Producer
     */
    private $producer;

    public function __construct(Producer $producer)
    {
        $this->producer = $producer;
    }

    public function logException(Exception $exception)
    {
        $body = [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage()
        ];
        
        $this->producer->produce($this, json_encode($body));
    }

    protected function getHeaders(): array
    {
        return [
            'content-type' => self::CONTENT_TYPE_APPLICATION_JSON,
            'delivery-mode' => self::DELIVERY_MODE_PERSISTENT,
        ];
    }

    protected function getExchangeName(): string
    {
        return 'logExchange';
    }

    protected function getRoutingKey(): string
    {
        return ''; // we have fanout exchange so routing key can be empty
    }
}
```

### Consumer example

```php
<?php declare(strict_types=1);

namespace App\Service\Consumer;

use Bunny\Message;
use Portiny\RabbitMQ\Consumer\AbstractConsumer;

final class LogConsumer extends AbstractConsumer
{
    protected function getQueueName(): string
    {
        return 'log';
    }
    
    protected function process(Message $message): int
    {
        $exception = json_decode($message->content);

        echo $exception->message;
        // do something with $exception

        return self::MESSAGE_ACK;
    }
}
```

Then register example classes into DI container (syntax depends on your framework).

```yaml
# Nette Framework (for integration use https://github.com/portiny/graphql-nette)
services:
    - App\Service\Exchange\LogExchange
    - App\Service\Queue\LogQueue
    - App\Service\Producer\LogProducer
    - App\Service\Consumer\LogConsumer
```

```yaml
# Symfony Framework (for integration use https://github.com/portiny/graphql-symfony)
services:
    App\Service\Exchange\LogExchange: ~
    App\Service\Queue\LogQueue: ~
    App\Service\Producer\LogProducer: ~
    App\Service\Consumer\LogConsumer: ~
```

### Declaring exchange and queue into RabbitMQ

You have two ways how to declare exchange and queue into RabbitMQ. If you use [Symfony/Console](https://symfony.com/doc/current/components/console.html) then simply run command `rabbitmq:declare`.

```bash
bin/console rabbitmq:declare
```

If you don't use [Symfony/Console](https://symfony.com/doc/current/components/console.html) simply inject `Portiny\RabbitMQ\BunnyManager` into your class and call `declare()` method.

```php
<?php declare(strict_types=1);

namespace App\Service;

use Portiny\RabbitMQ\BunnyManager;

class MyDeclarator
{
    /**
     * @var BunnyManager
     */
    private $bunnyManager;

    public function __construct(BunnyManager $bunnyManager)
    {
        $this->bunnyManager = $bunnyManager;
    }

    public function declare(): void
    {
        $this->bunnyManager->declare();
    }
}
```

### How to publish?

It's simple! Just call `logException` method on `App\Service\Producer\LogProducer` e.g.:

```php
<?php declare(strict_types=1);

namespace App\Control;

use App\Service\Producer\LogProducer;
use Exception;
use Nette\Application\UI\Control;

final class ClickControl extends Control
{
	/**
	 * @var LogProducer
	 */
	private $logProducer;

	public function __construct(LogProducer $logProducer)
	{
		$this->logProducer = $logProducer;
	}

	public function handleClick(int $x, int $y)
	{
		try {
			// some logic
		
		} catch (Exception $exception) {
			$this->logProducer->logException($exception);
		}
	}
}
```

### How to consume?

At first add alias to `TestConsumer` (syntax depends on your framework).

```yaml
# Nette Framework (for integration use https://github.com/portiny/graphql-nette)
rabbitmq:
    aliases:
        logger: App\Service\Consumer\LogConsumer
```

```yaml
# Symfony Framework (for integration use https://github.com/portiny/graphql-symfony)
parameters:
    portiny.rabbitmq.aliases: 
        logger: App\Service\Consumer\LogConsumer
```

Use [Symfony/Console](https://symfony.com/doc/current/components/console.html) and simply run command `rabbitmq:consume`.

#### Consuming for limited message amount

Consumer `App\Service\Consumer\LogConsumer` will consume 20 messages and then die.

```bash
bin/console rabbitmq:consume logger --messages 20
```

#### Consuming for limited time

Consumer `App\Service\Consumer\LogConsumer` will consumimg for 30 seconds and then die.

```bash
bin/console rabbitmq:consume logger --time 30
```
