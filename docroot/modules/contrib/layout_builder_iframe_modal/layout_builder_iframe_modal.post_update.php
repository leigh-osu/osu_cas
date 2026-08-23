<?php

/**
 * @file
 * Post-update functions for Layout Builder iFrame Modal.
 */

/**
 * Remove duplicate route prefixes.
 */
function layout_builder_iframe_modal_post_update_remove_duplicate_route_prefixes(): void {
  $affected_routes = [
    'layout_builder.layout_builder.configure_section',
    'layout_builder.layout_builder.remove_section',
    'layout_builder.layout_builder.remove_block',
    'layout_builder.layout_builder.add_section',
    'layout_builder.layout_builder.add_block',
    'layout_builder.layout_builder.update_block',
    'layout_builder.layout_builder.move_sections_form',
    'layout_builder.layout_builder.move_block_form',
    'layout_builder.layout_builder.translate_block',
    'layout_builder.layout_builder.translate_inline_block',
  ];

  $config = \Drupal::service('config.factory')->getEditable('layout_builder_iframe_modal.settings');
  $routes = $config->get('layout_builder_iframe_routes');

  foreach ($routes as &$route) {
    if (in_array($route, $affected_routes)) {
      $route = substr($route, 15);
    }
  }

  $config->set('layout_builder_iframe_routes', $routes)->save();
}

/**
 * Add default value for custom_routes config if empty.
 */
function layout_builder_iframe_modal_post_update_custom_routes_config(): void {
  $config = \Drupal::service('config.factory')->getEditable('layout_builder_iframe_modal.settings');
  $custom_routes = $config->get('custom_routes');
  if (empty($custom_routes)) {
    $config->set('custom_routes', [])->save();
  }
}
