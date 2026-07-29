<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns;

use Psr\Log\AbstractLogger;

/**
 * A logger stub.
 */
class LoggerStub extends AbstractLogger {

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    // Do nothing.
  }

}
