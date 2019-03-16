# Information

This bundle provide integration of [Portiny/RabbitMQ](https://github.com/portiny/rabbitmq/) into Symfony Framework.


## Installation

The simplest way to install Portiny/RabbitMQ-Symfony is using  [Composer](http://getcomposer.org/):

```sh
$ composer require portiny/rabbitmq-symfony
```

Enable the bundle by adding it to the list of registered bundles in the `app/AppKernel.php` file of your project.

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new Portiny\RabbitMQSymfony\PortinyRabbitMQSymfonyBundle(),
        ];

        // ...
    }

    // ...
}
```

## Configuration

Enable auto-tagging for queries and mutations classes:

```yaml
services:
    instanceof:
        Portiny\RabbitMQ\Consumer\AbstractConsumer:
            tags: ['portiny.rabbitmq.consumer']
        Portiny\RabbitMQ\Exchange\AbstractExchange:
            tags: ['portiny.rabbitmq.exchange']
        Portiny\RabbitMQ\Producer\AbstractProducer:
            tags: ['portiny.rabbitmq.producer']
        Portiny\RabbitMQ\Queue\AbstractQueue:
            tags: ['portiny.rabbitmq.queue']
```

Available configuration might look like this:

```yml
parameters:
    portiny.rabbitmq.aliases: []
    portiny.rabbitmq.connection:
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
```


## Usage

See official [Portiny/RabbitMQ documentation](https://github.com/portiny/rabbitmq/blob/master/docs/en/index.md#usage).
