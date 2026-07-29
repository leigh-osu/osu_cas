<?php

/**
 * @file
 * Contains database additions to drupal-9.4.0.bare.standard.php.gz.
 *
 * This fixture enables the views_aggregator module by setting system
 * configuration values in the {config} and {key_value} tables, then adds the
 * views_aggregator View from views_aggregator 2.0.2 to the {config} table.
 *
 * This fixture is intended for use in testing views_aggregator_update_10200().
 *
 * @see https://www.drupal.org/project/views_aggregator/issues/3480009
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Set the views_aggregator DB schema version.
$connection->insert('key_value')
  ->fields([
    'collection' => 'system.schema',
    'name' => 'views_aggregator',
    'value' => 'i:10100;',
  ])
  ->execute();

// Update core.extension to enable views_aggregator.
$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
// phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize.InsecureUnserialize
$extensions = unserialize($extensions);
$extensions['module']['views_aggregator'] = 0;
$connection->update('config')
  ->fields([
    'data' => serialize($extensions),
  ])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Install the View configuration that we're trying to test.
$config = Yaml::decode(file_get_contents(__DIR__ . '/va-fixture-table-2.0.2.yml'));
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'views.view.va_fixture_table',
    'data' => serialize($config),
  ])
  ->execute();
