<?php

namespace Drupal\views_migration\Plugin\migrate\views\field\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The Views Migrate plugin for "views_bulk_operations" Field Handlers.
 *
 * @MigrateViewsField(
 *   id = "path",
 *   field_ids = {
 *     "path",
 *   },
 *   core = {7},
 * )
 */
class Path extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    $data = $this->getEntityByDataTable($handler_config['table']);
    $handler_config['plugin_id'] = 'entity_link';
    $handler_config['entity_type'] = $handler_config['table'];
    $handler_config['field'] = 'view_' . $handler_config['table'];
    if (!is_null($data)) {
      $handler_config['table'] = $data['base_table'];
      $handler_config['entity_type'] = $data['entity_id'];
      $handler_config['field'] = 'view_' . $data['entity_id'];
    }
    if (isset($handler_config['absolute'])) {
      $handler_config['alter']['absolute'] = $handler_config['absolute'];
    }
  }

}
