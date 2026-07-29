<?php

/**
 * @file
 * Post update functions for Date AP Style module.
 */

/**
 * Migrate configuration and fix data types for existing installations.
 *
 * This comprehensive post-update function handles:
 * - Migration from old config name (date_ap_style.dateapstylesettings)
 *   to new (date_ap_style.settings)
 * - Cleanup of invalid configuration keys
 * - Conversion of string/integer values to proper boolean types in main config
 * - Conversion of string/integer values to proper boolean types in
 *   entity view display configs
 * - Handles both fresh installations and existing sites.
 */
function date_ap_style_post_update_migrate_and_cleanup_config() {
  $config_factory = \Drupal::configFactory();
  $config_storage = \Drupal::service('config.storage');
  $messages = [];

  // Step 1: Migrate from old config name if it exists.
  $old_config = $config_factory->getEditable('date_ap_style.dateapstylesettings');
  if (!$old_config->isNew()) {
    $old_data = $old_config->getRawData();
    $new_config = $config_factory->getEditable('date_ap_style.settings');

    // Boolean fields that need type conversion.
    $boolean_fields = [
      'always_display_year',
      'use_today',
      'cap_today',
      'display_day',
      'display_time',
      'hide_date',
      'time_before_date',
      'display_noon_and_midnight',
      'capitalize_noon_and_midnight',
      'use_all_day',
      'month_only',
    ];

    // Convert values to proper types.
    foreach ($old_data as $key => $value) {
      if (in_array($key, $boolean_fields)) {
        $new_config->set($key, (bool) $value);
      }
      else {
        $new_config->set($key, $value);
      }
    }

    $new_config->save();
    $old_config->delete();
    $messages[] = 'Migrated configuration from date_ap_style.dateapstylesettings to date_ap_style.settings';
  }

  // Step 2: Clean up the main configuration
  // (handles both migrated and existing configs)
  $main_config = $config_factory->getEditable('date_ap_style.settings');
  if (!$main_config->isNew()) {
    $raw_data = $main_config->getRawData();
    $main_changed = FALSE;

    // Remove invalid keys.
    if (array_key_exists('_core', $raw_data)) {
      $main_config->clear('_core');
      $main_changed = TRUE;
    }
    if (array_key_exists('date_ap_style_settings', $raw_data)) {
      $main_config->clear('date_ap_style_settings');
      $main_changed = TRUE;
    }

    // Convert any remaining string/integer boolean values to proper booleans.
    $boolean_fields = [
      'always_display_year',
      'use_today',
      'cap_today',
      'display_day',
      'display_time',
      'hide_date',
      'time_before_date',
      'display_noon_and_midnight',
      'capitalize_noon_and_midnight',
      'use_all_day',
      'month_only',
    ];

    foreach ($boolean_fields as $field) {
      $value = $main_config->get($field);
      if ($value === '1' || $value === 1) {
        $main_config->set($field, TRUE);
        $main_changed = TRUE;
      }
      elseif ($value === '0' || $value === 0) {
        $main_config->set($field, FALSE);
        $main_changed = TRUE;
      }
    }

    if ($main_changed) {
      $main_config->save();
      $messages[] = 'Cleaned up main configuration and fixed data types';
    }
  }

  // Step 3: Fix entity view display field formatter settings.
  $view_display_configs = $config_storage->listAll('core.entity_view_display.');
  $boolean_fields = [
    'always_display_year',
    'use_today',
    'cap_today',
    'display_day',
    'display_time',
    'hide_date',
    'time_before_date',
    'display_noon_and_midnight',
    'capitalize_noon_and_midnight',
    'use_all_day',
    'month_only',
  ];

  $updated_displays = [];

  foreach ($view_display_configs as $config_name) {
    $config = $config_factory->getEditable($config_name);
    $content = $config->get('content');
    $changed = FALSE;

    if (!empty($content)) {
      foreach ($content as $field_name => $field_config) {
        // Check if this field uses one of our AP style formatters.
        if (isset($field_config['type']) &&
            in_array($field_config['type'], [
              'timestamp_ap_style',
              'daterange_ap_style',
            ])) {
          $settings = $field_config['settings'] ?? [];
          $settings_changed = FALSE;

          // Convert string/integer values to proper booleans.
          foreach ($boolean_fields as $boolean_field) {
            if (isset($settings[$boolean_field])) {
              $current_value = $settings[$boolean_field];

              if ($current_value === '1' || $current_value === 1) {
                $settings[$boolean_field] = TRUE;
                $settings_changed = TRUE;
              }
              elseif ($current_value === '0' || $current_value === 0) {
                $settings[$boolean_field] = FALSE;
                $settings_changed = TRUE;
              }
            }
          }

          if ($settings_changed) {
            $config->set("content.{$field_name}.settings", $settings);
            $changed = TRUE;
          }
        }
      }
    }

    if ($changed) {
      $config->save();
      $updated_displays[] = $config_name;
    }
  }

  if (!empty($updated_displays)) {
    $messages[] = t('Fixed data types in @count entity view display configurations', [
      '@count' => count($updated_displays),
    ]);
  }

  // Return combined message.
  if (!empty($messages)) {
    return implode('. ', $messages) . '.';
  }

  return t('No configuration updates needed - site is already up to date.');
}
