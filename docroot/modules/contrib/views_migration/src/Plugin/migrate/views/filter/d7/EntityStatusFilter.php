<?php

namespace Drupal\views_migration\Plugin\migrate\views\filter\d7;

/**
 * The Views Migrate plugin for "date_filter" Filter Handlers.
 *
 * @MigrateViewsFilter(
 *   id = "entity_status_filter",
 *   field_ids = {
 *      "status"
 *   },
 *   core = {7},
 * )
 */
class EntityStatusFilter extends DefaultFilter {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (!isset($handler_config['operator'])) {
      $handler_config['operator'] = '=';
    }
    if (isset($handler_config['exposed']) && !isset($handler_config['expose']['operator'])) {
      $handler_config['expose']['operator'] = '=';
    }
    parent::alterHandlerConfig($handler_config);
  }

}
