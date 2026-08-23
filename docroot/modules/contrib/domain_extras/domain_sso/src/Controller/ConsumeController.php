<?php

namespace Drupal\domain_sso\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consume a token and log in the user.
 */
class ConsumeController extends ControllerBase {

  /**
   * Consume a token and log in the user.
   */
  public function consume(Request $request) {
    $token = $request->query->get('token');
    if (empty($token)) {
      return new Response('no token', Response::HTTP_BAD_REQUEST);
    }

    try {
      [$payload_b64, $signature] = explode('.', $token, 2);
      $payload_str = base64_decode($payload_b64);
      $payload = json_decode($payload_str, TRUE);
    }
    catch (\Exception $e) {
      return new Response('invalid token: ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
    }

    // Verify signature.
    $expected_signature = hash_hmac('sha256', $payload_str, Settings::getHashSalt() . $payload['nonce']);
    if (!hash_equals($expected_signature, $signature)) {
      return new Response('invalid token signature', Response::HTTP_BAD_REQUEST);
    }

    // Check expiration.
    if ($payload['exp'] < time()) {
      return new Response('token expired', Response::HTTP_BAD_REQUEST);
    }

    // Validate exp automatically via JWT lib. Additional checks:
    $nonce = $payload['nonce'] ?? NULL;
    if (empty($nonce)) {
      return new Response('missing nonce', Response::HTTP_BAD_REQUEST);
    }

    // Prevent replay: check cache for nonce.
    $cache_key = 'domain_sso_handshake_nonce.' . $nonce;
    $entry = $this->cache()->get($cache_key);
    if (!$entry) {
      return new Response('nonce not found or already used', Response::HTTP_BAD_REQUEST);
    }

    // One-time use: delete nonce.
    $this->cache()->delete($cache_key);

    // Map payload to a local user.
    $uid = $payload['uid'] ?? NULL;
    if (empty($uid)) {
      return new Response('token contains no user id', Response::HTTP_BAD_REQUEST);
    }

    $user = $this->entityTypeManager()->getStorage('user')->load($uid);
    if (!$user) {
      return new Response('user not found', Response::HTTP_BAD_REQUEST);
    }

    // Log user in programmatically.
    user_login_finalize($user);

    // Redirect to the final destination or home if none specified.
    $target = $request->query->get('target');
    if (empty($target)) {
      $domain_storage = $this->entityTypeManager()->getStorage('domain');
      /** @var \Drupal\domain\DomainInterface $domain */
      $domain = $domain_storage->load($payload['domain']);
      $url = Url::fromRoute('<front>')
        ->setOption('base_url', rtrim($domain->getPath(), '/'));
      return new TrustedRedirectResponse($url->toString());
    }
    else {
      return new RedirectResponse($target);
    }
  }

}
