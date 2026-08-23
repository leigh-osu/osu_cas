<?php

/**
 * @file
 * Post update functions for CAS Attributes module.
 */

/**
 * Don't serialize the mappings.
 */
function cas_attributes_post_update_unserialize_mappings() {
  $config = \Drupal::configFactory()->getEditable('cas_attributes.settings');
  $config
    ->set('field.mappings', array_filter(unserialize($config->get('field.mappings'))))
    ->set('role.mappings', (array) unserialize($config->get('role.mappings')))
    ->save(TRUE);
}

/**
 * Set a default value for token_allowed_attributes.
 */
function cas_attributes_post_update_default_value_token_allowed_attributes() {
  $config = \Drupal::configFactory()->getEditable('cas_attributes.settings');
  $config
    ->set('token_allowed_attributes', [])
    ->save(TRUE);
}
