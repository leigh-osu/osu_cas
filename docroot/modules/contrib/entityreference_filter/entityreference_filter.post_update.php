<?php

/**
 * @file
 * Post update functions for Views Reference Filter.
 */

declare(strict_types=1);

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\views\ViewEntityInterface;

/**
 * Casts stored filter values to strings to match the updated config schema.
 */
function entityreference_filter_post_update_cast_filter_values_to_string(?array &$sandbox = NULL): void {
  $plugin_ids = [
    'entityreference_filter_view_result',
    'entityreference_filter_view_result_search_api',
  ];

  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($plugin_ids): bool {
    $changed = FALSE;
    $displays = $view->get('display');

    foreach ($displays as &$display) {
      if (empty($display['display_options']['filters'])) {
        continue;
      }
      foreach ($display['display_options']['filters'] as &$filter) {
        if (!in_array($filter['plugin_id'] ?? '', $plugin_ids, TRUE)) {
          continue;
        }
        if (empty($filter['value']) || !is_array($filter['value'])) {
          continue;
        }
        foreach ($filter['value'] as $key => $value) {
          if (!is_string($value)) {
            $filter['value'][$key] = (string) $value;
            $changed = TRUE;
          }
        }
      }
      unset($filter);
    }
    unset($display);

    if ($changed) {
      $view->set('display', $displays);
    }

    return $changed;
  });
}

/**
 * Enables the Search API integration submodule when Search API is installed.
 *
 * The Search API filter plugin moved into the entityreference_filter_search_api
 * submodule. Enable it on sites that already use Search API so their existing
 * Search API entity reference filters keep working.
 */
function entityreference_filter_post_update_enable_search_api_submodule(): void {
  $module_handler = \Drupal::moduleHandler();
  if ($module_handler->moduleExists('search_api') && !$module_handler->moduleExists('entityreference_filter_search_api')) {
    \Drupal::service('module_installer')->install(['entityreference_filter_search_api']);
  }
}
