<?php

namespace Drupal\toc_js_test_module\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for toc_js_test_module.
 */
class TocJsTestModuleHooks {

  /**
   * Implements hook_preprocess_node().
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(&$variables) {
    if ($variables['node']->getType() === 'article') {
      $variables['attributes']['class'][] = 'node';
    }
  }

}
