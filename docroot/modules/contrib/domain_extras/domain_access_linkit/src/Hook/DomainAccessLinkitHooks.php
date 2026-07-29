<?php

namespace Drupal\domain_access_linkit\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain_access_linkit\Plugin\Linkit\Matcher\AssignedDomainsNodeMatcher;

/**
 * Hook implementations for domain_access_linkit.
 */
class DomainAccessLinkitHooks {

  /**
   * Implements hook_node_grants_alter().
   */
  #[Hook('node_grants_alter')]
  public function nodeGrantsAlter(
    array &$grants,
    AccountInterface $account,
    $op,
  ): void {
    $temp_grants = drupal_static(
      AssignedDomainsNodeMatcher::TEMP_ASSIGNED_DOMAINS_NODE_GRANTS
    );
    if (!empty($temp_grants)) {
      foreach ($temp_grants as $realm => $gids) {
        if (!isset($grants[$realm])) {
          $grants[$realm] = [];
        }
        $grants[$realm] = array_unique(
          array_merge($grants[$realm], $gids)
        );
      }
    }
  }

}
