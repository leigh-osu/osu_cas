<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\LinksPropType;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Builds normalized links trees from menus, for source plugins.
 *
 * The composing class is expected to extend SourcePluginBase. If the class is
 * capable of injecting services from the container, it should inject
 * 'menu.link_tree' and 'entity_type.manager' and assign them to
 * $this->menuLinkTree and $this->entityTypeManager.
 */
trait MenuTreeSourceTrait {

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface|null
   */
  protected $menuLinkTree;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * Builds the normalized links tree of a menu.
   *
   * @param string $menu_id
   *   The menu ID.
   * @param int $level
   *   The initial visibility level.
   * @param int $depth
   *   The number of levels to display, 0 meaning no limit.
   *
   * @return array
   *   Links items, as expected by the 'links' prop type.
   */
  protected function buildMenuTree(string $menu_id, int $level = 1, int $depth = 0): array {
    $menu_link_tree = $this->menuLinkTree();
    $parameters = new MenuTreeParameters();
    $parameters->setMinDepth($level);

    // When the depth is configured to zero, there is no depth limit. When depth
    // is non-zero, it indicates the number of levels that must be displayed.
    // Hence this is a relative depth that we must convert to an actual
    // (absolute) depth, that may never exceed the maximum depth.
    if ($depth > 0) {
      $parameters->setMaxDepth(
        min($level + $depth - 1, $menu_link_tree->maxDepth())
      );
    }

    $tree = $menu_link_tree->load($menu_id, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    $tree = $menu_link_tree->transform($tree, $manipulators);
    $tree = $menu_link_tree->build($tree);
    if (!\array_key_exists('#items', $tree)) {
      return [];
    }
    $variables = [
      'items' => $tree['#items'],
    ];
    $this->moduleHandler->invokeAll('preprocess_menu', [&$variables]);
    return LinksPropType::normalize($variables['items'], $this->getPropDefinition());
  }

  /**
   * Gets the menus list.
   *
   * @return array
   *   Menu labels, keyed by menu ID and sorted by label.
   */
  protected function getMenuList(): array {
    $menus = [];
    foreach ($this->entityTypeManager()->getStorage('menu')->loadMultiple() as $id => $menu) {
      $menus[$id] = $menu->label();
    }
    asort($menus);
    return $menus;
  }

  /**
   * Adds the cache tags of menus to a render element.
   *
   * @param array $element
   *   The render element.
   * @param array $menu_ids
   *   The menu IDs.
   *
   * @return array
   *   The render element.
   */
  protected function addMenuCacheTags(array $element, array $menu_ids): array {
    $cache = CacheableMetadata::createFromRenderArray($element);
    foreach ($menu_ids as $menu_id) {
      $cache->addCacheTags(['config:system.menu.' . $menu_id]);
    }
    $cache->applyTo($element);
    return $element;
  }

  /**
   * Merges the config dependencies on menus.
   *
   * @param array $dependencies
   *   The dependencies, altered by reference.
   * @param array $menu_ids
   *   The menu IDs.
   */
  protected function menuConfigDependencies(array &$dependencies, array $menu_ids): void {
    $storage = $this->entityTypeManager()->getStorage('menu');
    foreach ($menu_ids as $menu_id) {
      $menu = $storage->load($menu_id);
      if (!$menu) {
        continue;
      }
      SourcePluginBase::mergeConfigDependencies($dependencies, [$menu->getConfigDependencyKey() => [$menu->getConfigDependencyName()]]);
    }
  }

  /**
   * Gets the menu link tree service.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeInterface
   *   The menu link tree service.
   */
  protected function menuLinkTree() {
    if (!isset($this->menuLinkTree)) {
      $this->menuLinkTree = \Drupal::service('menu.link_tree');
    }
    return $this->menuLinkTree;
  }

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager() {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

}
