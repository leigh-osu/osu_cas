<?php

namespace Drupal\osu_story;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Swaps the redirect repository for the auto-forward-aware subclass.
 *
 * RedirectRepository has no interface (consumers type-hint the class), so
 * gating external-story redirects behind the osu_story.settings toggle is
 * done by subclassing rather than decorating.
 */
class OsuStoryServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('redirect.repository')) {
      $container->getDefinition('redirect.repository')
        ->setClass(OsuStoryRedirectRepository::class);
    }
  }

}
