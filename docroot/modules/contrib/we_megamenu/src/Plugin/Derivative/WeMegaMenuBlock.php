<?php

namespace Drupal\we_megamenu\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\system\Entity\Menu;

/**
 * Provides blocks which belong to Drupal 8 Mega Menu.
 */
class WeMegaMenuBlock extends DeriverBase {
  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $menus = Menu::loadMultiple();
    foreach ($menus as $menu_id => $menu) {
      $this->derivatives[$menu_id] = $base_plugin_definition;
      /** @var \Drupal\system\MenuInterface $menu */
      $this->derivatives[$menu_id]['admin_label'] = $menu->label();
    }
    return $this->derivatives;
  }

}
