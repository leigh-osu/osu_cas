<?php

namespace Drupal\views_migration\Plugin\migrate\views\relationship\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Relationship plugin for Entity Fields.
 *
 * This plugin is used to prepare the Views `relationships` display options for
 * migration if:
 *  - The Handler Field represents an Entity Field.
 *  - The is no Migrate Views Relationship plugin for the Entity Field's type.
 *
 * @MigrateViewsRelationship(
 *   id = "d7_location_address_entity_relationship",
 *   entity_field_types = {
 *      "address"
 *   },
 *   core = {7},
 * )
 */
class LocationAddressEntityRelationship extends MigrateViewsHandlerPluginBase {

  /**
   * Override the parent to declare the correct var type.
   *
   * @var \Drupal\views_migration\Plugin\migrate\views\SourceHandlerEntityFieldInfoProvider
   */
  protected $infoProvider;

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (str_ends_with($handler_config['field'], '_lid')) {
      $handler_config['remove_this_item'] = TRUE;
    }
  }

}
