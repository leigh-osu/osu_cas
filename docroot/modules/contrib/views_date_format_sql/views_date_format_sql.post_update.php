<?php

/**
 * @file
 * Provides post-update routines.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\views\Entity\View;

/**
 * Updates views to recalculate dependencies.
 */
function views_date_format_sql_post_update_fix_unneeded_dependencies(&$sandbox) {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'view', function (View $view) {
      $dependencies = $view->getDependencies();
      return !empty($dependencies)
        && $dependencies['modules'] !== NULL
        && in_array('views_date_format_sql', $dependencies['modules']);
    });
}
