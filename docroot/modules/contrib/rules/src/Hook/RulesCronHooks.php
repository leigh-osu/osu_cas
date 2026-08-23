<?php

declare(strict_types=1);

namespace Drupal\rules\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\rules\Event\SystemCronEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Hook implementations used for scheduled execution.
 */
final class RulesCronHooks {

  /**
   * Constructs a new RulesCronHooks service.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event_dispatcher service.
   */
  public function __construct(
    protected EventDispatcherInterface $dispatcher,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    if ($this->dispatcher->hasListeners(SystemCronEvent::EVENT_NAME)) {
      $event = new SystemCronEvent();
      $this->dispatcher->dispatch($event, SystemCronEvent::EVENT_NAME);
    }
  }

}
