<?php

namespace Drupal\views_migration\Plugin\migrate\views\filter\d7;

/**
 * The Views Migrate plugin for "user_roles" Filter Handlers.
 *
 * @MigrateViewsFilter(
 *   id = "taxonomy_index_tid",
 *   entity_field_types = {
 *    "entity_reference"
 *   },
 *   core = {7},
 * )
 */
class EntityReferenceFilter extends DefaultFilter {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    parent::alterHandlerConfig($handler_config);
    if ($handler_config['operator'] == 'in') {
      $handler_config['operator'] = 'or';
    }
  }

}
