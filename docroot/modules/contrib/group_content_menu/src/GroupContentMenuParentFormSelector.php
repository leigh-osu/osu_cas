<?php

namespace Drupal\group_content_menu;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuParentFormSelector;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Group content menu implementation of the menu parent form selector service.
 *
 * The form selector is a list of all appropriate menu links.
 */
class GroupContentMenuParentFormSelector extends MenuParentFormSelector {

  /**
   * Determine if menu is a group menu.
   *
   * @var bool
   */
  protected $isGroupMenu = FALSE;

  /**
   * The inner service from decorator.
   *
   * @var \Drupal\Core\Menu\MenuParentFormSelectorInterface
   */
  protected $inner;

  /**
   * Constructs a \Drupal\group_content_menu\GroupContentMenuParentFormSelector.
   *
   * @param \Drupal\Core\Menu\MenuParentFormSelectorInterface $inner
   *   The inner service from decorator.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(MenuParentFormSelectorInterface $inner, MenuLinkTreeInterface $menu_link_tree, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation) {
    parent::__construct($menu_link_tree, $entity_type_manager, $string_translation);
    $this->inner = $inner;
  }

  /**
   * {@inheritdoc}
   */
  public function parentSelectElement($menu_parent, $id = '', ?array $menus = NULL) {
    if (\str_contains($menu_parent, GroupContentMenuInterface::MENU_PREFIX)) {
      $this->isGroupMenu = TRUE;
    }
    if (empty($menus)) {
      if ($this->isGroupMenu) {
        $edited_menu_name = \explode(':', $menu_parent)[0];
        $group_content_menu_id = str_replace(GroupContentMenuInterface::MENU_PREFIX, '', $edited_menu_name);
        $menus = $this->getMenuOptions([$group_content_menu_id]);
      }
      else {
        $menus = $this->inner->getMenuOptions();
      }
    }
    $element = $this->inner->parentSelectElement($menu_parent, $id, $menus);
    // Add the group content list tag in case a menu is created, deleted, etc.
    $element['#cache']['tags'] = $element['#cache']['tags'] ?? [];
    $element['#cache']['tags'] = Cache::mergeTags($element['#cache']['tags'], ['group_content_list']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMenuOptions(?array $menu_names = NULL) {
    if (!$this->isGroupMenu) {
      return $this->inner->getMenuOptions($menu_names);
    }
    if (!$route_group = \Drupal::routeMatch()->getParameter('group')) {
      return [];
    }
    $group_content_menus = $this->entityTypeManager->getStorage('group_content_menu')->loadMultiple($menu_names);
    $options = [];
    /** @var \Drupal\group_content_menu\GroupContentMenuInterface[] $menus */
    foreach ($group_content_menus as $group_content_menu) {
      $group_relationships = $this->entityTypeManager->getStorage('group_content')->loadByEntity($group_content_menu);
      if ($group_relationships) {
        $menu_group = array_pop($group_relationships)->getGroup();
        if ($menu_group->id() === $route_group->id()) {
          $options[GroupContentMenuInterface::MENU_PREFIX . $group_content_menu->id()] = $group_content_menu->label() . " ({$menu_group->label()})";
        }
      }
    }
    return $options;
  }

}
