# Information

This extension provide integration of [Symfony Console](https://github.com/symfony/console) into Nette Framework.


## Installation

The simplest way to install Portiny/Console is using  [Composer](http://getcomposer.org/):

```sh
$ composer require portiny/console
```

Enable the extension at your neon config file.

```yml
extensions:
    console: Portiny\Console\Adapter\Nette\DI\ConsoleExtension
```

## Minimal configuration

This extension can be configured by `console` section. The minimal configuration might look like this:

```yml
console:
    url: https://www.portiny.org
```

The `url` key contains reference url for Nette class `LinkGenerator`. Without this `url` key will be all links generated under CLI look like this `http:///some-link...`.


## Usage

Create `bin/console` script for executing console commands. 

```php
#!/usr/bin/env php
<?php declare(strict_types = 1);

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

exit(App\Bootstrap::boot()
    ->createContainer()
    ->getByType(Application::class)
    ->run());
```

Now we can run console via command `php bin/console` and if we make it executable with `chmod +x bin/console` we can run console only via `bin/console`.

## Create new command

Official documentation for [Symfony/Console](http://symfony.com/doc/current/console.html#creating-a-command) describes how to create a command. 

The only important thing after creating the command is to register it in the DI container in your neon config file.

```yml
services:
    - App\Command\MyFirstCommand
```

Then you can execute command via `bin/console my-first-command-name`.
