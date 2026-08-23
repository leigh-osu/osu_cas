<?php

namespace Drupal\views_migration\Plugin\migrate\views\filter\d7;

/**
 * The Views Migrate plugin for "entity_type_filter" Filter Handlers.
 *
 * @MigrateViewsFilter(
 *   id = "entity_type_filter",
 *   field_ids = {
 *     "type"
 *   },
 *   core = {7},
 * )
 */
class EntityTypeFilter extends DefaultFilter {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (!isset($handler_config['operator'])) {
      $handler_config['operator'] = 'in';
    }
    if (isset($handler_config['exposed']) && !isset($handler_config['expose']['operator'])) {
      $handler_config['expose']['operator'] = 'in';
    }
    parent::alterHandlerConfig($handler_config);
  }

}
