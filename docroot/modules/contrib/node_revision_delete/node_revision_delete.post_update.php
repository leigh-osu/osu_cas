<?php

/**
 * @file
 * Post update functions for node_revision_delete.
 */

/**
 * Add bulk_delete_threshold setting.
 */
function node_revision_delete_post_update_add_bulk_delete_threshold(): void {
  $settings = \Drupal::configFactory()->getEditable('node_revision_delete.settings');
  if ($settings->get('bulk_delete_threshold') === NULL) {
    $settings->set('bulk_delete_threshold', 50);
    $settings->save();
  }
}
