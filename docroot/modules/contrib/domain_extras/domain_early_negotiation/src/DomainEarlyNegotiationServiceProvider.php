<?php

namespace Drupal\domain_early_negotiation;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Overrides the middleware priority from configuration.
 */
class DomainEarlyNegotiationServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $config_storage = BootstrapConfigStorageFactory::get();
    $settings = $config_storage->read('domain_early_negotiation.settings');
    $container->setParameter(
      'domain_early_negotiation.priority',
      (int) ($settings['priority'] ?? 220)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('http_middleware.domain_negotiation')) {
      $definition = $container->getDefinition('http_middleware.domain_negotiation');
      $definition->clearTag('http_middleware');
      $definition->addTag('http_middleware', [
        'priority' => $container->getParameter('domain_early_negotiation.priority'),
      ]);
    }
  }

}
