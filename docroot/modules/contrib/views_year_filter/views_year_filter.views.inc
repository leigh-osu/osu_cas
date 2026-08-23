<?php

/**
 * @file
 * Views hooks.
 */

use Drupal\views_year_filter\Plugin\views\filter\ViewsYearFilterDate;
use Drupal\views_year_filter\Plugin\views\filter\ViewsYearFilterDatetime;
use Drupal\views_year_filter\Plugin\views\filter\ViewsSearchApiYearFilterDate;

/**
 * Implements hook_views_plugins_filter_alter().
 */
function views_year_filter_views_plugins_filter_alter(&$info): void {
  // For date field let the smart_date module add the granularity
  // in this case use year granularity from smart_date.
  if (isset($info['date']) && $info['date']['provider'] !== 'smart_date') {
    $info['date']['class'] = ViewsYearFilterDate::class;
    $info['date']['provider'] = 'views_year_filter';
  }
  if (isset($info['datetime'])) {
    $info['datetime']['class'] = ViewsYearFilterDatetime::class;
    $info['date']['provider'] = 'views_year_filter';
  }
  if (isset($info['search_api_date']) && \Drupal::moduleHandler()->moduleExists('search_api')) {
    $info['search_api_date']['class'] = ViewsSearchApiYearFilterDate::class;
    $info['date']['provider'] = 'views_year_filter';
  }
}
