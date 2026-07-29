<?php

namespace Drupal\we_megamenu\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derivative class that provides the menu listing under megamenu config.
 */
class MegamenuListing extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a MegamenuListing instance.
   */
  public function __construct($base_plugin_id, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (!empty($this->derivatives)) {
      return $this->derivatives;
    }

    $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();

    foreach ($menus as $menu_id => $menu) {
      $this->derivatives[$menu_id] = [
        'title' => $menu->label(),
        'route_name' => 'we_megamenu.admin.configure',
        'route_parameters' => [
          'menu_name' => $menu_id,
        ],
      ] + $base_plugin_definition;

    }

    return $this->derivatives;
  }

}
