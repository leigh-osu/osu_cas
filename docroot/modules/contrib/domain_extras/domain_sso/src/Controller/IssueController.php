<?php

namespace Drupal\domain_sso\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issue a token for the current user.
 */
class IssueController extends ControllerBase {

  /**
   * Issue a token for the current user.
   */
  public function issue(Request $request) {
    $current_user = $this->currentUser();

    if ($current_user->isAuthenticated()) {

      // Get the target domain from the query string.
      $domain_id = $request->query->get('domain');
      if (empty($domain_id)) {
        return new Response('Missing target domain parameter', Response::HTTP_BAD_REQUEST);
      }
      else {
        $domain_storage = $this->entityTypeManager()->getStorage('domain');
        /** @var \Drupal\domain\DomainInterface $domain */
        $domain = $domain_storage->load($domain_id);
        if (is_null($domain)) {
          return new Response('Invalid target domain', Response::HTTP_BAD_REQUEST);
        }
      }

      $uid = $current_user->id();

      // Nonce.
      $nonce = bin2hex(random_bytes(16));

      // Payload.
      $now = time();
      $payload = [
        'uid' => $uid,
        'iat' => $now,
        // 60 seconds.
        'exp' => $now + 60,
        'nonce' => $nonce,
        'domain' => $domain->id(),
      ];

      // Serialize payload.
      $payload_str = json_encode($payload);

      // Sign it.
      $signature = hash_hmac('sha256', $payload_str, Settings::getHashSalt() . $nonce);

      // Build token string.
      $token = base64_encode($payload_str) . '.' . $signature;

      // Store nonce in Cache with 5-minute TTL for automatic cleanup.
      $this->cache()->set('domain_sso_handshake_nonce.' . $nonce, [], $now + 300);

      $query = ['token' => $token];

      // Get the destination URL from the query string.
      $target = $request->query->get('target');
      if (!empty($target)) {
        $query['target'] = $target;
      }

      $consume_url = Url::fromRoute('domain_sso.handshake.consume', [], [
        'absolute' => TRUE,
        'query' => $query,
        'domain' => $domain,
      ])->toString();

      // Redirect the browser to the consumer URL.
      return new RedirectResponse($consume_url);

    }
    else {

      // If the user is not logged in, redirect to the login page.
      $login_url = Url::fromRoute('user.login', [], [
        'query' => ['destination' => $request->getRequestUri()],
      ])->toString();

      return new RedirectResponse($login_url);

    }

  }

}
