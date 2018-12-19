# Information

This extension provide integration of [Bunny/Bunny](https://github.com/jakubkulhan/bunny) into Nette Framework.


## Installation

The simplest way to install Portiny/RabbitMQ is using  [Composer](http://getcomposer.org/):

```sh
$ composer require portiny/rabbitmq
```

Enable the extension at your neon config file.

```yml
extensions:
    rabbitmq: Portiny\RabbitMQ\Adapter\Nette\DI\RabbitMQExtension
```


## Minimal configuration

This extension can be configured by `rabbitmq` section like this:

```yml
rabbitmq:
    host: localhost
    port: 5672
    # etc.
```

If your RabbitMQ service use default configuration you do not need to configure anything here.


## Full configuration

```yml
rabbitmq:
    connection:
        host: 127.0.0.1
        port: 5672
        user: guest
        password: guest
        vhost: /
        timeout: 1
        heartbeat: 60.0
        persistent: false
        path: /
        tcp_nodelay: false
    aliases:
        logger: App/Consumers/LogConsumer # now you can use key "logger" at "rabbitmq:consume" command as consumer name
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
    public function getName(): string
    {
        return 'logExchange';
    }
    
    public function getType(): string
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
    public function getName(): string
    {
        return 'log';
    }

    public function getBindings(): array
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

    public function getHeaders(): array
    {
        return [
            'content-type' => self::CONTENT_TYPE_APPLICATION_JSON,
            'delivery-mode' => self::DELIVERY_MODE_PERSISTENT,
        ];
    }

    public function getExchangeName(): string
    {
        return 'logExchange';
    }

    public function getRoutingKey(): string
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
    public function getQueueName(): string
    {
        return 'log';
    }
    
    public function process(Message $message): int
    {
        $exception = json_decode($message->content);

        echo $exception->message;
        // do something with $exception

        return self::MESSAGE_ACK;
    }
}
```

Then register example classes into Nette DI container.

```yaml
services:
    - App\Service\Exchange\LogExchange
    - App\Service\Queue\LogQueue
    - App\Service\Producer\LogProducer
    - App\Service\Consumer\LogConsumer
```

### Declaring exchange and queue into RabbitMQ

You have two ways how to declare exchange and queue into RabbitMQ. If you use [Symfony/Console](https://symfony.com/doc/current/components/console.html) integrated via e.g. [Portiny/Console](https://github.com/portiny/console), [Contributte/Console](https://github.com/contributte/console) or [Kdyby/Console](https://github.com/Kdyby/Console) then simply run command `rabbitmq:declare`.

```bash
# bin/console rabbitmq:declare
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

### How to consume?

You have two ways how to trigger consuming messages from RabbitMQ. If you use [Symfony/Console](https://symfony.com/doc/current/components/console.html) integrated via e.g. [Portiny/Console](https://github.com/portiny/console), [Contributte/Console](https://github.com/contributte/console) or [Kdyby/Console](https://github.com/Kdyby/Console) then simply run command `rabbitmq:consume`.

#### Consuming for limited message amount

Consumer `App\Service\Consumer\LogConsumer` will consume 20 messages and then die.

```bash
# bin/console rabbitmq:consume App\\Service\\Consumer\\LogConsumer --messages 20
```

#### Consuming for limited time

Consumer `App\Service\Consumer\LogConsumer` will consumimg for 30 seconds and then die.

```bash
# bin/console rabbitmq:consume App\\Service\\Consumer\\LogConsumer --time 30
```

If you don't like using FQDM at CLI (like me) then you can use aliases. :)

```yaml
rabbitmq:
    aliases:
        logger: App\Service\Consumer\LogConsumer
```

Then you can use alias "logger" at `rabbitmq:consume` command.

```bash
# bin/console rabbitmq:consume logger --time 30
```