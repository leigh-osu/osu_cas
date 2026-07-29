<?php

declare(strict_types=1);

namespace Drupal\preview_link;

/**
 * Provides service tasks for hooks.
 */
class PreviewLinkHookHelper {

  /**
   * Whether Preview Link is granting entity view access.
   *
   * @var bool
   */
  protected $grantAccess = TRUE;

  /**
   * Whether Preview Link is granting entity view access.
   */
  public function isPreviewLinkGrantingAccess(): bool {
    return $this->grantAccess;
  }

  /**
   * Set whether Preview Link is granting entity view access.
   */
  public function setPreviewLinkGrantingAccess(bool $access) {
    $this->grantAccess = $access;
  }

}
