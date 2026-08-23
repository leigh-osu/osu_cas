<?php

namespace Drupal\styleguide\Theme;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Styleguide Theme Negotiator.
 */
class StyleguideThemeNegotiator implements ThemeNegotiatorInterface, ContainerInjectionInterface {

  /**
   * Theme machine name.
   *
   * @var string
   */
  public $themeName;

  public function __construct(protected ThemeExtensionList $themeExtensionList) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.theme'),
    );
  }

  /**
   * Whether this theme negotiator should be used to set the theme.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match object.
   *
   * @return bool
   *   TRUE if this negotiator should be used or FALSE to let other negotiators
   *   decide.
   */
  public function applies(RouteMatchInterface $route_match) {
    if (strpos((string) $route_match->getRouteName(), 'styleguide.') === FALSE) {
      return FALSE;
    }

    $themes = $this->themeExtensionList->reset()->getList();
    foreach ($themes as &$theme) {
      if (!empty($theme->info['hidden'])) {
        continue;
      }
      if ($theme->status) {
        $route_name = $route_match->getRouteName();
        if ($route_name == 'styleguide.' . $theme->getName() || $route_name == 'styleguide.maintenance_page.' . $theme->getName()) {
          $this->themeName = $theme->getName();
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Determine the active theme for the request.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match object.
   *
   * @return string|null
   *   Returns the active theme name, else return NULL.
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    return $this->themeName;
  }

}
