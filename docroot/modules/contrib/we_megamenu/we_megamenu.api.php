<?php

/**
 * @file
 * Hooks and documentation for megamenu.
 */

/**
 * Alters the menu link tree manipulator list.
 *
 * This hook allows any module or theme to alter the list
 * of menu link tree manipulators. An example use case is
 * filtering the menu items by current user language by
 * menu_manipulator module.
 *
 * @param array $manipulators
 *   The menu link tree manipulators to apply. Each is an array with keys:
 *   - callable: a callable or a string that can be resolved to a callable
 *     by \Drupal\Core\Controller\ControllerResolverInterface::getControllerFromDefinition()
 *   - args: optional array of arguments to pass to the callable after $tree.
 * @param string $menu_name
 *   The menu name. The alter hooks implementation is responsible for checking
 *   which menu(s) the manipulators should apply to.
 */
function hook_megamenu_manipulators_alter(array &$manipulators, $menu_name) {
  if ($menu_name == 'admin') {
    $manipulators[] = [
      'callable' => 'menu.default_tree_manipulators:generateIndexAndSort',
    ];
  }
}
