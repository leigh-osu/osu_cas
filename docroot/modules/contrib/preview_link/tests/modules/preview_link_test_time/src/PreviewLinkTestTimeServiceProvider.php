<?php

declare(strict_types=1);

namespace Drupal\preview_link_test_time;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Alter service container definitions.
 *
 * @see https://www.drupal.org/docs/8/api/services-and-dependency-injection/altering-existing-services-providing-dynamic-services
 */
final class PreviewLinkTestTimeServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->addCompilerPass(new PreviewLinkTestCompilerPass());
  }

}
