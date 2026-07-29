<?php

namespace Drupal\shurly\Plugin\Block;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a block for creating short urls.
 *
 * @Block(
 *   id = "shurl_create_form",
 *   admin_label = @Translation("Short URL form"),
 *   category = @Translation("Forms")
 * )
 */
class ShurlyBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $current_path = \Drupal::service('path.current')->getPath();
    $path_args = explode('/', $current_path);

    return AccessResultAllowed::allowedIfHasPermission($account, 'create short URLs')
      ->andIf(AccessResultAllowed::allowedIf($path_args[0] != 'shurly'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\shurly\Form\ShurlyCreateForm');

    return $form;
  }

}
