<?php

declare(strict_types=1);

namespace Drupal\rules\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\rules\Event\UserLoginEvent;
use Drupal\rules\Event\UserLogoutEvent;
use Drupal\user\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Hook implementations to create and dispatch User Events.
 */
final class RulesUserHooks {

  /**
   * Constructs a new RulesUserHooks service.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event_dispatcher service.
   */
  public function __construct(
    protected EventDispatcherInterface $dispatcher,
  ) {}

  /**
   * Implements hook_user_login().
   */
  #[Hook('user_login')]
  public function userLogin(UserInterface $account): void {
    // Set the account twice on the event: as the main subject but also in the
    // list of arguments.
    if ($this->dispatcher->hasListeners(UserLoginEvent::EVENT_NAME)) {
      $event = new UserLoginEvent($account);
      $this->dispatcher->dispatch($event, UserLoginEvent::EVENT_NAME);
    }
  }

  /**
   * Implements hook_user_logout().
   */
  #[Hook('user_logout')]
  public function userLogout(AccountInterface $account): void {
    // Set the account twice on the event: as the main subject but also in the
    // list of arguments.
    if ($this->dispatcher->hasListeners(UserLogoutEvent::EVENT_NAME)) {
      $event = new UserLogoutEvent($account, ['account' => $account]);
      $this->dispatcher->dispatch($event, UserLogoutEvent::EVENT_NAME);
    }
  }

}
