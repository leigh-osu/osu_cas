<?php

namespace Drupal\domain_menu_extras;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\domain_menu_extras\Menu\DomainAwareLocalTaskManager;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Swaps core menu plugin manager classes for domain-aware variants.
 *
 * Uses setClass() + addArgument() so we inherit core's argument list and
 * stay resilient to upstream constructor changes — we only append the
 * extra DomainNegotiationContext reference our subclasses need.
 *
 * @see \Drupal\domain_menu_extras\Menu\DomainAwareLocalTaskManager
 * @see https://www.drupal.org/project/domain_extras/issues/3588108
 */
class DomainMenuExtrasServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('plugin.manager.menu.local_task')) {
      $definition = $container->getDefinition('plugin.manager.menu.local_task');
      $definition->setClass(DomainAwareLocalTaskManager::class);
      $definition->addArgument(new Reference('domain.negotiation_context'));
    }
  }

}
