<?php

declare(strict_types=1);

namespace Drupal\Tests\node_revision_delete\Traits;

use Drupal\Component\Datetime\Time;

/**
 * Test helper service.
 */
class TimePatcher extends Time {

  /**
   * The number of seconds to patch time.
   *
   * @var int
   */
  private static int $patch = 0;

  /**
   * Sets the number of seconds to patch time.
   *
   * @param int $patch
   *   The number of seconds to advance time. Can be negative.
   */
  public static function setPatch(int $patch): void {
    self::$patch = $patch;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime(): int {
    return parent::getRequestTime() + self::$patch;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestMicroTime(): float {
    return parent::getRequestMicroTime() + self::$patch;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTime(): int {
    return parent::getCurrentTime() + self::$patch;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentMicroTime(): float {
    return parent::getCurrentMicroTime() + self::$patch;
  }

}
