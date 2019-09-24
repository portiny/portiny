# Information

This extension provide integration of [Portiny/RabbitMQ](https://github.com/portiny/rabbitmq/) into Nette Framework.


## Installation

The simplest way to install Portiny/RabbitMQ-Nette is using  [Composer](http://getcomposer.org/):

```sh
$ composer require portiny/rabbitmq-nette
```

Enable the extension at your neon config file.

```yml
extensions:
    rabbitmq: Portiny\RabbitMQNette\DI\RabbitMQExtension
```


## Minimal configuration

This extension can be configured by `rabbitmq` section like this:

```yml
rabbitmq:
    connection:
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

See official [Portiny/RabbitMQ documentation](https://github.com/portiny/rabbitmq/blob/master/docs/en/index.md#usage).
