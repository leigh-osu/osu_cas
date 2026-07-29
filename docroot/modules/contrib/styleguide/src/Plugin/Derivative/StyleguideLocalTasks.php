<?php

namespace Drupal\styleguide\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Styleguide local tasks.
 */
class StyleguideLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ThemeExtensionList $themeExtensionList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('config.factory'),
      $container->get('extension.list.theme'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $default_theme = $this->configFactory->get('system.theme')->get('default');
    $themes = $this->themeExtensionList->reset()->getList();
    $weight = 0;

    foreach ($themes as &$theme) {
      if (!empty($theme->info['hidden'])) {
        continue;
      }
      if ($theme->status) {
        $route_name = 'styleguide.' . $theme->getName();
        $this->derivatives[$route_name] = $base_plugin_definition + [
          'title' => $theme->info['name'],
          'route_name' => $route_name,
          'parent_id' => 'styleguide.page',
          'weight' => $weight++,
        ];
        if ($default_theme == $theme->getName()) {
          $this->derivatives[$route_name]['route_name'] = 'styleguide.page';
        }
      }
    }

    return $this->derivatives;
  }

}
