<?php

declare(strict_types=1);

namespace Drupal\group_content_menu\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\GroupAccessResult;
use Symfony\Component\Routing\Route;

final class TranslationAccess {

  public function __construct(
    private readonly AccessInterface $inner,
    private readonly ContextRepositoryInterface $contextRepository,
  ) {}

  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $context = $this->contextRepository->getRuntimeContexts(['group'])['group'];
    assert($context instanceof ContextInterface);
    $group = $context->getContextValue();

    $groupAccess = GroupAccessResult::allowedIfHasGroupPermission($group, $account, 'manage group_content_menu menu item translations');
    if ($groupAccess->isAllowed()) {
      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }

}
