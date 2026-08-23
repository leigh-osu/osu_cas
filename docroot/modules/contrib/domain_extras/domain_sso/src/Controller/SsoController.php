<?php

namespace Drupal\domain_sso\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Issue a token for the current user.
 */
class SsoController extends ControllerBase {

  /**
   * SSO login.
   */
  public function login(Request $request) {
    /** @var \Drupal\domain\DomainStorageInterface $domain_storage */
    $domain_storage = $this->entityTypeManager()->getStorage('domain');
    /** @var \Drupal\domain\DomainInterface $default_domain */
    $default_domain = $domain_storage->loadDefaultDomain();
    // Get current domain ID.
    $domain_id = static::getActiveDomainId();
    // Get the referer to know from which URL we come.
    $referer = $request->headers->get('referer');
    // Redirect to your handshake route inside Drupal.
    $handshake_url = Url::fromRoute('domain_sso.handshake.issue', [], [
      'absolute' => TRUE,
      'query' => ['domain' => $domain_id, 'target' => $referer],
    ])->setOption('base_url', rtrim($default_domain->getPath(), '/'));
    return new TrustedRedirectResponse($handshake_url->toString());
  }

  /**
   * Get the current domain ID.
   */
  protected static function getActiveDomainId() {
    return \Drupal::service('domain.negotiation_context')->getDomainId();
  }

}
