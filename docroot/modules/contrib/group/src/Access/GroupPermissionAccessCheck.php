<?php

namespace Drupal\group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Context\GroupRouteContext;
use Symfony\Component\Routing\Route;

/**
 * Determines access to routes based on group permissions.
 */
class GroupPermissionAccessCheck implements AccessInterface {

  /**
   * The group route context.
   *
   * @var \Drupal\group\Context\GroupRouteContext
   */
  protected $groupRouteContext;

  /**
   * Constructs a new GroupPermissionAccessCheck.
   *
   * @param \Drupal\group\Context\GroupRouteContext $group_route_context
   *   Group context provider.
   */
  public function __construct(GroupRouteContext $group_route_context) {
    $this->groupRouteContext = $group_route_context;
  }

  /**
   * Checks access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    $permission = $route->getRequirement('_group_permission');

    // Don't interfere if no group available.
    $group = $this->groupRouteContext->getBestCandidate();
    if (!$group) {
      return AccessResult::neutral();
    }

    // Allow to conjunct the permissions with OR ('+') or AND (',').
    $split = explode(',', $permission);
    if (count($split) > 1) {
      return GroupAccessResult::allowedIfHasGroupPermissions($group, $account, $split, 'AND');
    }
    else {
      $split = explode('+', $permission);
      return GroupAccessResult::allowedIfHasGroupPermissions($group, $account, $split, 'OR');
    }
  }

}
