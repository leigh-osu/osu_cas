<?php

declare(strict_types=1);

namespace Drupal\layout_builder_iframe_modal\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to scroll to the position before rebuild.
 *
 * @ingroup ajax
 */
class ScrollToBlockCommand implements CommandInterface {

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'scrollToBlock',
    ];
  }

}
