<?php

declare(strict_types=1);

namespace Drupal\rules\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations used by Rules for email.
 */
final class RulesMailHooks {

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    $message['subject'] .= str_replace(["\r", "\n"], '', $params['subject']);
    $message['body'][] = $params['message'];
  }

}
