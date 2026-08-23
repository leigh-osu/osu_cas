<?php

namespace Drupal\group_content_menu;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper class to handle altering node forms.
 *
 * @package Drupal\GroupContentMenu
 */
class NodeFormAlter implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('menu.parent_form_selector'),
      $container->get('current_user'),
    );
  }

  /**
   * Alter node forms to add GroupContentMenu options where appropriate.
   *
   * @param array $form
   *   A form array as from hook_form_alter().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   A form state object as from hook_form_alter().
   *
   * @deprecated in group_content_menu:2.0.6 and is removed from
   *   group_content_menu:4.0.0. Instead, use
   *   Drupal\group_content_menu\Hook\NodeFormAlter
 */
  public function alter(array &$form, FormStateInterface $form_state): void {
    \Drupal::service('group_content_menu.node_form_alter')->alter($form, $form_state);
  }

}
