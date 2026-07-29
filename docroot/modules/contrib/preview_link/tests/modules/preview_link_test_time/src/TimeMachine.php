<?php

declare(strict_types=1);

namespace Drupal\preview_link_test_time;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\State\StateInterface;

/**
 * Service used to simulate time.
 */
class TimeMachine implements TimeInterface {

  /**
   * TimeMachine constructor.
   *
   * @param \Closure $state
   *   State.
   */
  public function __construct(
    protected \Closure $state,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime(): int {
    return $this->getTime()->getTimestamp();
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestMicroTime() {
    return (float) $this->getTime()->getTimestamp();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTime() {
    return $this->getTime()->getTimestamp();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentMicroTime() {
    return (float) $this->getTime()->getTimestamp();
  }

  /**
   * Sets time.
   *
   * @param \DateTimeInterface $dateTime
   *   Sets the time.
   */
  public function setTime(\DateTimeInterface $dateTime): void {
    if ($dateTime instanceof \DateTime) {
      $dateTime = \DateTimeImmutable::createFromMutable($dateTime);
    }
    $this->getState()->set('preview_link_test_time_machine', $dateTime);
  }

  /**
   * Get the time from state.
   *
   * @returns \DateTimeImmutable
   *   The date time.
   *
   * @throws \LogicException
   *   When date time was not set.
   */
  protected function getTime(): \DateTimeImmutable {
    $dateTime = $this->getState()->get('preview_link_test_time_machine');
    if (!isset($dateTime)) {
      return new \DateTimeImmutable();
    }
    return $dateTime;
  }

  /**
   * Get the service from the closure.
   *
   * @return \Drupal\Core\State\StateInterface
   */
  public function getState(): StateInterface {
    return ($this->state)();
  }

}
