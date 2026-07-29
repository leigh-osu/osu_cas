<?php

namespace Drupal\layout_builder_iframe_modal;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Helper for config.
 */
class IframeModalHelper {

  /**
   * IframeModalHelper constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory) {}

  /**
   * Check if the given route is enabled.
   *
   * @param string $route_name
   *   Name of the route to check.
   *
   * @return bool
   *   The route is enabled and links should open in the iframe modal.
   */
  public function isModalRoute(string $route_name): bool {
    $config = $this->configFactory->get('layout_builder_iframe_modal.settings');
    $layout_builder_routes = $config->get('layout_builder_iframe_routes') ?? [];
    $custom_routes = $config->get('custom_routes') ?? [];
    $enabledRoutes = array_merge($layout_builder_routes, $custom_routes);
    return in_array($route_name, $enabledRoutes);
  }

}
