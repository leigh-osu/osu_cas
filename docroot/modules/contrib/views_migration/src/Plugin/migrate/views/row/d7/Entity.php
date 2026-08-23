<?php

namespace Drupal\views_migration\Plugin\migrate\views\row\d7;

/**
 * The Migrate Views plugin for entity Views Row Plugins.
 *
 * @MigrateViewsRow(
 *   id = "entity",
 *   plugin_ids = {
 *     "entity"
 *   },
 *   core = {7},
 * )
 */
class Entity extends DefaultRow {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    $row_plugin_map = [
      'node' => 'entity:node',
      'users' => 'entity:user',
      'taxonomy_term' => 'entity:taxonomy_term',
      'file_managed' => 'entity:file',
    ];
    $display_options['row_plugin'] = $row_plugin_map[$this->entityType];
    parent::prepareDisplayOptions($display_options);
  }

}
