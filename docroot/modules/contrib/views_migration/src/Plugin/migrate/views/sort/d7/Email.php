<?php

namespace Drupal\views_migration\Plugin\migrate\views\sort\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Sort plugin.
 *
 * This plugin is used to prepare the Views `sort` display options for
 * migration when no other migrate plugin exists for the current sort plugin.
 *
 * @MigrateViewsSort(
 *   id = "email",
 *   entity_field_types = {
 *    "email"
 *   },
 *   core = {7}
 * )
 */
class Email extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (isset($handler_config['table'])) {
      $handler_config['table'] = $this->getViewsHandlerTableMigratePlugin($handler_config['table'])->getNewTableValue($this->infoProvider);
    }
    $handler_config['field'] = substr($handler_config['field'], 0, -6) . '_value';
    $this->alterEntityIdField($handler_config);
    $this->configurePluginId($handler_config, 'sort');
  }

}
