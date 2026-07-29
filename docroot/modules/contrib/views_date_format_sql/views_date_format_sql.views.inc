<?php

/**
 * @file
 * This file contains the hook to assign this handler as default to date fields.
 */

use Drupal\field\Entity\FieldStorageConfig;

/**
 * Implements hook_views_data_alter().
 */
function views_date_format_sql_views_data_alter(&$data) {
  // Loops through fields definitions looking for timestamp fields and changes
  // the standard date handler with our own.
  foreach ($data as &$table) {
    // Skip files because they have very specific fields.
    if (isset($table['table']['provider']) && $table['table']['provider'] === 'file') {
      continue;
    }

    foreach ($table as &$field) {
      // Check to see if the field being used here is a field_api timestamp.
      if (!empty($field['field']['entity_type']) && !empty($field['field']['field_name'])) {
        $field_storage = FieldStorageConfig::loadByName($field['field']['entity_type'], $field['field']['field_name']);
        if ($field_storage && $field_storage->getType() == 'timestamp') {
          $field['field']['id'] = 'views_date_format_sql_field';
        }
      }
      elseif (!empty($field['argument']['entity_type']) && !empty($field['argument']['field_name'])) {
        $field_storage = FieldStorageConfig::loadByName($field['argument']['entity_type'], $field['argument']['field_name']);
        if ($field_storage && $field_storage->getType() == 'timestamp') {
          $field['argument']['id'] = 'views_date_format_sql_argument';
        }
      }
      elseif (!empty($field['field']['id']) && !empty($field['argument']['id'])) {
        if ($field['field']['id'] === 'field' && $field['argument']['id'] === 'date') {
          $field['field']['id'] = 'views_date_format_sql_field';
        }
      }
    }
  }
}
