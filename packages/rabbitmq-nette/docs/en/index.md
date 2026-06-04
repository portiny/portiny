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

If your RabbitMQ service use default configuration you do not need to configure anything here. When nothing is
configured, a single connection named `default` with default values is created automatically.


## Full configuration (single connection)

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


## Multiple connections

You can configure several named connections (for example to talk to multiple vhosts or brokers). Each connection gets
its own separated topology — only the consumers, exchanges and queues that belong to it are declared on it.

```yml
rabbitmq:
    defaultConnection: default
    connections:
        default:
            host: 127.0.0.1
            vhost: /default
        marketing:
            host: 127.0.0.1
            vhost: /marketing
        billing:
            host: broker-2.internal
            port: 5672
            vhost: /billing
    aliases:
        invoice: App\Consumers\InvoiceConsumer
```

Each connection accepts the same options as the single `connection` section (`host`, `port`, `user`, `password`,
`vhost`, `timeout`, `heartbeat`, `persistent`, `path`, `tcp_nodelay`). Any option you omit falls back to the default
value. `defaultConnection` selects which connection is used when no connection name is given (defaults to `default`).


### Assigning a component to a connection

A consumer, exchange or queue declares which connection it belongs to by overriding `getConnectionName()`. The default
implementation returns `default`, so existing components keep working without any change.

```php
use Portiny\RabbitMQ\Consumer\AbstractConsumer;

final class InvoiceConsumer extends AbstractConsumer
{
    public function getConnectionName(): string
    {
        return 'billing';
    }

    // ...
}
```

The extension reads `getConnectionName()` via reflection at compile time and registers the component only on the
matching connection's manager. If a component references a connection that is not defined under
`rabbitmq.connections`, a clear exception is thrown at compile time asking you to define it.


### Console commands

The `rabbitmq:consume` and `rabbitmq:declare` commands accept an optional `--connection` (`-c`) option to target a
specific connection:

```sh
$ bin/console rabbitmq:consume invoice --connection=billing
$ bin/console rabbitmq:declare --connection=billing
```

When `--connection` is omitted, the command resolves the consumer across all connections (and declaration runs for
every connection).


## Backward compatibility

The previous flat configuration keeps working unchanged:

- The old `rabbitmq: connection: {...}` form is automatically mapped to a connection named `default`.
- If you configure nothing, a `default` connection with default values is created.
- Named connections are fully opt-in — existing Nette applications run without any configuration change.


## Usage

See official [Portiny/RabbitMQ documentation](https://github.com/portiny/rabbitmq/blob/master/docs/en/index.md#usage).
</content>
