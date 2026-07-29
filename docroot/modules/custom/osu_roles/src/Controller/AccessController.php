<?php

namespace Drupal\osu_roles\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Provide access control logic for OSU Roles.
 */
class AccessController {

  /**
   * Checks access for managing themes in OSU Drupal.
   *
   * Users with DX Administrator, Administrator or User id of 1 can perform
   * these actions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The Access result.
   */
  public function osuAdminOnly(AccountInterface $account): AccessResult {
    if ($account->hasRole('dx_administrator') || $account->hasRole('administrator') || $account->id() == 1) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

  /**
   * Checks if the user can manage theme settings.
   *
   * DX Administrator can configure all themes. Other users can only
   *   configure the "Madrone" theme if they have the "administer madrone theme"
   *   permission.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string|null $theme
   *   Optional theme to check against.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function themeSettingsOnly(AccountInterface $account, $theme = NULL): AccessResult {
    if ($account->hasRole('dx_administrator') || $account->hasRole('administrator') || $account->id() == 1) {
      return AccessResult::allowed();
    }

    if ($theme === 'madrone' && $account->hasPermission('administer madrone theme')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
