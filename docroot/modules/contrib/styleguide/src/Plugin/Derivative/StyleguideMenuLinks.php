<?php

namespace Drupal\styleguide\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Styleguide menu links.
 */
class StyleguideMenuLinks extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(protected ThemeExtensionList $themeExtensionList) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('extension.list.theme'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $themes = $this->themeExtensionList->reset()->getList();

    foreach ($themes as $theme) {
      if (!empty($theme->info['hidden'])) {
        continue;
      }
      if ($theme->status) {
        $route_name = 'styleguide.' . $theme->getName();
        $this->derivatives[$route_name] = $base_plugin_definition + [
          'title' => $theme->info['name'],
          'route_name' => $route_name,
        ];
      }
    }

    return $this->derivatives;
  }

}
