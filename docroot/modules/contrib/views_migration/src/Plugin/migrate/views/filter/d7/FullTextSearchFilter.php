<?php

namespace Drupal\views_migration\Plugin\migrate\views\filter\d7;

/**
 * The Views Migrate plugin for "full_text_search_api" Filter Handlers.
 *
 * @MigrateViewsFilter(
 *   id = "full_text_search_api",
 *   field_ids = {
 *     "search_api_views_fulltext"
 *   },
 *   core = {7},
 * )
 */
class FullTextSearchFilter extends DefaultFilter {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    parent::alterHandlerConfig($handler_config);
    $mapOperator = [
      'OR' => 'or',
      'AND' => 'and',
      'NOT' => 'not',
    ];
    $handler_config['field'] = 'search_api_fulltext';
    if (isset($mapOperator[$handler_config['operator']])) {
      $handler_config['operator'] = $mapOperator[$handler_config['operator']];
    }
    if (isset($handler_config['expose'])) {
      if (isset($handler_config['expose']['remember_roles']) && is_array($handler_config['expose']['remember_roles'])) {
        $handler_config['expose']['remember'] = TRUE;
      }
    }
  }

}
