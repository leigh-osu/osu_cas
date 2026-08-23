<?php

namespace Drupal\views_migration\Plugin\migrate\views\filter\d7;

/**
 * The Views Migrate plugin for "date_filter" Filter Handlers.
 *
 * @MigrateViewsFilter(
 *   id = "datetime_filter",
 *   entity_field_types = {
 *      "datetime"
 *   },
 *   core = {7},
 * )
 */
class DateTimeFilter extends DefaultFilter {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    parent::alterHandlerConfig($handler_config);
    $base_entity_type = $this->infoProvider->getViewBaseEntityType();
    $handler_config['plugin_id'] = 'datetime';
    if (!empty($handler_config['default_date'])) {
      $handler_config['value']['value'] = $handler_config['default_date'];
      $handler_config['value']['type'] = 'offset';
      unset($handler_config['default_date']);
    }
    $this->alterExposeSettings($handler_config);
  }

}
