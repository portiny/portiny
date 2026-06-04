<?php declare(strict_types = 1);

namespace Portiny\RabbitMQSymfony;

use Portiny\RabbitMQSymfony\DependencyInjection\Compiler\ConnectionAwareCompilerPass;
use Portiny\RabbitMQSymfony\DependencyInjection\PortinyRabbitMQSymfonyExtension;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PortinyRabbitMQSymfonyBundle extends Bundle
{

	public function build(ContainerBuilder $container): void
	{
		parent::build($container);

		// Runs late (after tags are collected) so per-connection topology can be wired up.
		$container->addCompilerPass(
			new ConnectionAwareCompilerPass(),
			PassConfig::TYPE_BEFORE_OPTIMIZATION
		);
	}

	/**
	 * Overridden to allow the short "portiny_rabbitmq" config alias, which does not match the
	 * underscored bundle name convention enforced by the default implementation.
	 */
	public function getContainerExtension(): ?ExtensionInterface
	{
		if ($this->extension === null) {
			$this->extension = new PortinyRabbitMQSymfonyExtension();
		}

		return $this->extension ?: null;
	}

}
