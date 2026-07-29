<?php

declare(strict_types=1);

namespace Drupal\preview_link_test;

use Drupal\Core\Logger\LogMessageParser;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * Sends logs to state.
 */
final class StateLogger implements LoggerInterface {

  use LoggerTrait;

  const STATE_LOGGER = 'state_preview_link_test_logs';

  /**
   * StateLogger constructor.
   */
  public function __construct(
    protected StateInterface $state,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    $logs = $this->getLogs();
    $messagePlaceholders = (new LogMessageParser())->parseMessagePlaceholders($message, $context);
    $logs[] = [
      $level,
      $message,
      $messagePlaceholders,
      // Whitelist context keys to prevent serializing container, etc.
      array_intersect_key($context, array_flip([
        'channel',
      ])),
    ];
    $this->state->set(static::STATE_LOGGER, $logs);
  }

  /**
   * Gets the captured logs.
   *
   * @return array
   *   An array of logs, which contain the arguments passed to log().
   */
  public function getLogs(): array {
    return $this->state->get(static::STATE_LOGGER, []);
  }

  /**
   * Deletes captured logs.
   */
  public function cleanLogs(): void {
    $this->state->delete(static::STATE_LOGGER);
  }

}
