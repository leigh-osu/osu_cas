<?php

namespace Drupal\osu_roles\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Modifies existing routes to apply specific access restrictions.
 *
 * This class extends RouteSubscriberBase to override the alterRoutes method.
 * It customizes routes related to theme installation, setting default themes,
 * theme uninstallation, global theme settings, and theme-specific settings.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritDoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Restrict theme installation to dx_administrator only.
    if ($route = $collection->get('system.theme_install')) {
      $route->setRequirement('_custom_access', '\Drupal\osu_roles\Controller\AccessController::osuAdminOnly');
    }
    // Restrict setting theme as default to dx_administrator only.
    if ($route = $collection->get('system.theme_set_default')) {
      $route->setRequirement('_custom_access', '\Drupal\osu_roles\Controller\AccessController::osuAdminOnly');
    }
    // Restrict theme uninstallation to dx_administrator only.
    if ($route = $collection->get('system.theme_uninstall')) {
      $route->setRequirement('_custom_access', '\Drupal\osu_roles\Controller\AccessController::osuAdminOnly');
    }
    // Restrict global theme settings.
    if ($route = $collection->get('system.theme_settings')) {
      $route->setRequirement('_custom_access', '\Drupal\osu_roles\Controller\AccessController::themeSettingsOnly');
    }
    // Restrict theme setting options to users with the permission to manage.
    if ($route = $collection->get('system.theme_settings_theme')) {
      $route->setRequirement('_custom_access', '\Drupal\osu_roles\Controller\AccessController::themeSettingsOnly');
      $route->setOption('parameters', [
        'theme' => ['type' => 'string', 'default_value' => NULL],
      ]);
    }
  }

}
