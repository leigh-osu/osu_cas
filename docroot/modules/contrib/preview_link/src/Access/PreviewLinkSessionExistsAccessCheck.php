<?php

declare(strict_types=1);

namespace Drupal\preview_link\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks whether the user has a session.
 */
class PreviewLinkSessionExistsAccessCheck implements AccessInterface {

  /**
   * Constructs a new PreviewLinkSessionExistsAccessCheck.
   *
   * @param \Drupal\Core\Session\SessionConfigurationInterface $sessionConfiguration
   *   The session configuration.
   */
  public function __construct(
    protected SessionConfigurationInterface $sessionConfiguration,
  ) {
  }

  /**
   * Checks whether the user has a session.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request, if available.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Whether the user has a session.
   */
  public function access(?Request $request = NULL): AccessResultInterface {
    return AccessResult::allowedIf($request !== NULL ? $this->sessionConfiguration->hasSession($request) : FALSE)
      ->addCacheContexts(['session.exists']);
  }

}
