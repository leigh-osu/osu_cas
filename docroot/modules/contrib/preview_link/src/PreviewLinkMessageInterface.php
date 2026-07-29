<?php

declare(strict_types=1);

namespace Drupal\preview_link;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Interface for common messenger functionality.
 */
interface PreviewLinkMessageInterface {

  /**
   * Get the message showing how the user can view the current page.
   *
   * @param \Drupal\Core\Url $destination
   *   The canonical URL of the entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A translatable string.
   */
  public function getGrantMessage(Url $destination): TranslatableMarkup;

}
