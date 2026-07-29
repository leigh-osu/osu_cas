<?php

declare(strict_types=1);

namespace Drupal\Tests\entityreference_filter\Traits;

use Drupal\Core\Config\FileStorage;

/**
 * Provides common helper methods for Entityreference filter module tests.
 */
trait EntityReferenceFilterTrait {

  /**
   * Create test views from config.
   *
   * @param array $views
   *   Views to create.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTestViews(array $views) {
    $storage = \Drupal::entityTypeManager()->getStorage('view');
    $module_handler = \Drupal::moduleHandler();
    $config_dir = \Drupal::service('extension.list.module')->getPath('entityreference_filter_test_config') . '/test_views';
    if (!is_dir($config_dir) || !$module_handler->moduleExists('entityreference_filter_test_config')) {
      return;
    }

    $file_storage = new FileStorage($config_dir);
    $available_views = $file_storage->listAll('views.view.');

    foreach ($views as $id) {
      $config_name = 'views.view.' . $id;
      if (in_array($config_name, $available_views, TRUE)) {
        $storage->create($file_storage->read($config_name))->save();
      }
    }

    // Rebuild the router once.
    \Drupal::service('router.builder')->rebuild();
  }

}
