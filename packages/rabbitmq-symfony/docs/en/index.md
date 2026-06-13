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
    _instanceof:
        Portiny\RabbitMQ\Consumer\AbstractConsumer:
            tags: ['portiny.rabbitmq.consumer']
        Portiny\RabbitMQ\Exchange\AbstractExchange:
            tags: ['portiny.rabbitmq.exchange']
        Portiny\RabbitMQ\Producer\AbstractProducer:
            tags: ['portiny.rabbitmq.producer']
        Portiny\RabbitMQ\Queue\AbstractQueue:
            tags: ['portiny.rabbitmq.queue']
```

### Semantic configuration

The recommended way to configure the bundle is the semantic `portiny_rabbitmq` configuration. It supports
multiple named connections (e.g. one per vhost), each with its own, **isolated** topology:

```yaml
portiny_rabbitmq:
    # Connection used by components that do not override getConnectionName().
    default_connection: default

    connections:
        default:
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
            tcp_keepalive: false     # enable kernel SO_KEEPALIVE on the socket
            tcp_keepalive_idle: 60   # TCP_KEEPIDLE: idle seconds before the first probe
            tcp_keepalive_interval: 30  # TCP_KEEPINTVL: seconds between probes
            tcp_keepalive_count: 4   # TCP_KEEPCNT: probes before the connection is dropped
            ssl: false               # bool, or an array of TLS context options

        billing:
            host: rabbit-billing.internal
            port: 5671
            user: billing
            password: '%env(RABBITMQ_BILLING_PASSWORD)%'
            vhost: /billing
            ssl:                     # ssl may also be an array of stream TLS options
                verify_peer: true
                cafile: /etc/ssl/certs/ca.pem

        analytics:
            host: rabbit-analytics.internal
            port: 5672
            user: analytics
            password: '%env(RABBITMQ_ANALYTICS_PASSWORD)%'
            vhost: /analytics

    # Optional global map of consumer aliases: alias => consumer FQCN.
    aliases:
        billing-invoices: App\RabbitMQ\Consumer\InvoiceConsumer
```

### TCP keepalive (long-lived consumers behind a load balancer)

Bunny is synchronous, so a heartbeat frame is only sent while the consumer is waiting for messages —
not while a message handler is running. A consumer that sits idle, or whose handler blocks for a long
time, therefore sends no traffic; an L4 load balancer in front of the broker (e.g. a cloud LB) drops the
connection on its TCP idle timeout, and the next operation fails with `Broken pipe or closed connection`.
Lowering the heartbeat is not an option when handlers can run longer than the heartbeat (the broker would
close the connection mid-job).

Set `tcp_keepalive: true` to enable kernel `SO_KEEPALIVE` on the connection socket. The keepalive probes
run in the OS independently of PHP, keeping every hop (including the LB) warm during both idle and blocking
periods, without touching the AMQP heartbeat. Tune `tcp_keepalive_idle` below the LB's idle timeout. It
works for TLS connections too: the socket is opened over plain TCP, keepalive is applied, then TLS is
negotiated on the same socket. Requires `ext-sockets` and the Linux `TCP_KEEPIDLE`/`TCP_KEEPINTVL`/
`TCP_KEEPCNT` socket options.

### Assigning a component to a connection

A consumer, exchange, queue or producer declares its connection by overriding `getConnectionName()`.
The bundle reads this value (via reflection) at compile time and wires the component only into the
matching connection's `BunnyManager`. This guarantees that `rabbitmq:declare` for a connection declares
**only that connection's** exchanges and queues.

```php
final class InvoiceConsumer extends AbstractConsumer
{
    public function getConnectionName(): string
    {
        return 'billing';
    }

    // ...
}
```

Components that do not override `getConnectionName()` return `'default'` and therefore belong to the
default connection.

You may also override the connection per service via the tag attribute (takes precedence over
`getConnectionName()`):

```yaml
services:
    App\RabbitMQ\Consumer\InvoiceConsumer:
        tags:
            - { name: 'portiny.rabbitmq.consumer', connection: 'billing' }
```

If a component references a connection that is not defined under `portiny_rabbitmq.connections`, the
container build fails with a clear error message so the misconfiguration is caught early.

### Console commands

Both commands accept an optional `--connection` (`-c`) option:

```sh
# Declare exchanges/queues for every connection
$ bin/console rabbitmq:declare

# Declare only the "billing" connection topology
$ bin/console rabbitmq:declare --connection=billing

# Consume from a specific connection
$ bin/console rabbitmq:consume billing-invoices --connection=billing
```

Without `--connection`, `rabbitmq:declare` declares all connections and `rabbitmq:consume` looks the
consumer up across all connections.

### Backward compatibility

The semantic configuration is fully opt-in. If you do **not** configure any `connections`, the bundle
automatically creates a single `default` connection from the legacy parameters
`portiny.rabbitmq.connection` and `portiny.rabbitmq.aliases`. Existing applications that rely on these
parameters keep working without any change:

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
        tcp_keepalive: false
```

Because components without an overridden `getConnectionName()` resolve to `'default'`, no component or
service change is required to upgrade.


## Usage

See official [Portiny/RabbitMQ documentation](https://github.com/portiny/rabbitmq/blob/master/docs/en/index.md#usage).
