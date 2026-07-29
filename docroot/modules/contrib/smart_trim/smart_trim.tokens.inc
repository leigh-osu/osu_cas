<?php

/**
 * @file
 * Builds placeholder replacement tokens for smart_trim related data.
 */

declare(strict_types=1);

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info_alter().
 */
function smart_trim_token_info_alter(array &$info): void {
  \Drupal::service('smart_trim.tokens')->tokenInfoAlter($info);
}

/**
 * Implements hook_tokens().
 */
function smart_trim_tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
  return \Drupal::service('smart_trim.tokens')->tokens($type, $tokens, $data, $options, $bubbleable_metadata);
}
