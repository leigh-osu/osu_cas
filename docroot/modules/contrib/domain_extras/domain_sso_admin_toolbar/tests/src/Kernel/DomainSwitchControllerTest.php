<?php

namespace Drupal\Tests\domain_sso_admin_toolbar\Kernel;

use Drupal\domain_sso_admin_toolbar\Controller\DomainSwitchController;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests DomainSwitchController path prefix handling.
 *
 * Verifies that when switching domains via the admin toolbar:
 * - The current domain's path prefix is stripped from the referer
 * - Same-hostname domains skip the SSO handshake
 * - Cross-hostname domains go through the SSO handshake.
 *
 * @group domain_sso_admin_toolbar
 */
#[Group('domain_sso_admin_toolbar')]
#[RunTestsInSeparateProcesses]
class DomainSwitchControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'domain',
    'domain_sso',
    'domain_sso_admin_toolbar',
  ];

  /**
   * The domain storage handler.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('domain');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);

    // Enable path prefix feature.
    $this->config('domain.settings')
      ->set('path_prefix', TRUE)
      ->save();
    $this->container = $this->container->get('kernel')->rebuildContainer();

    $this->domainStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('domain');

    // Create an authenticated user for the controller.
    $this->installConfig(['user']);
    $user = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->create([
        'uid' => 1,
        'name' => 'admin',
        'status' => 1,
      ]);
    $user->save();
    $this->container->get('current_user')->setAccount($user);
  }

  /**
   * Creates a request with a referer header and mock session.
   *
   * @param string $uri
   *   The request URI.
   * @param string|null $referer
   *   The referer URL, or NULL for no referer.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   A request with a mock session attached.
   */
  protected function createRequest(string $uri, ?string $referer = NULL): Request {
    $request = Request::create($uri);
    $request->setSession(
      new Session(new MockArraySessionStorage()),
    );
    if ($referer) {
      $request->headers->set('referer', $referer);
    }
    return $request;
  }

  /**
   * Pushes a request onto the request stack and negotiates.
   *
   * Sets the request as current, runs domain negotiation, and
   * returns the controller instance.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to use.
   *
   * @return \Drupal\domain_sso_admin_toolbar\Controller\DomainSwitchController
   *   The controller instance.
   */
  protected function setupRequest(Request $request): DomainSwitchController {
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);

    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->setRequestDomain($request->getHttpHost(), TRUE);

    return DomainSwitchController::create($this->container);
  }

  /**
   * Tests same-host prefix domains skip SSO handshake.
   *
   * Creates two domains on the same hostname with different path
   * prefixes. When switching between them, the controller should
   * redirect directly (no SSO) since they share session cookies.
   * The target URL should have the source prefix stripped and the
   * target prefix applied.
   */
  public function testSameHostPrefixSkipsSso() {
    // Two domains on the same hostname, different prefixes.
    $domain_a = $this->domainStorage->create([
      'id' => 'example_en',
      'hostname' => 'example.com',
      'name' => 'English',
      'scheme' => 'https',
      'status' => 1,
      'is_default' => TRUE,
      'path_prefix' => 'en',
    ]);
    $domain_a->save();

    $domain_b = $this->domainStorage->create([
      'id' => 'example_fr',
      'hostname' => 'example.com',
      'name' => 'French',
      'scheme' => 'https',
      'status' => 1,
      'path_prefix' => 'fr',
    ]);
    $domain_b->save();

    // Simulate being on domain A (/en/) with referer pointing
    // to /en/admin/content.
    $request = $this->createRequest(
      'https://example.com/en/admin/domain-sso-switch/example_fr',
      'https://example.com/en/admin/content',
    );
    $controller = $this->setupRequest($request);
    $response = $controller->switchDomain('example_fr');

    // Same hostname: should redirect directly, not through SSO.
    $target_url = $response->getTargetUrl();
    $this->assertStringNotContainsString(
      'domain-sso/handshake',
      $target_url,
      'Same-host domains skip SSO handshake.',
    );

    // Target URL should use domain B's prefix, not domain A's.
    $this->assertStringContainsString(
      'example.com/fr/',
      $target_url,
      'Target URL uses the target domain prefix.',
    );
    $this->assertStringNotContainsString(
      '/en/',
      $target_url,
      'Source domain prefix is stripped from target URL.',
    );
  }

  /**
   * Tests cross-host switch goes through SSO handshake.
   *
   * Creates two domains on different hostnames. When switching
   * between them, the controller should redirect through the SSO
   * handshake since they do not share session cookies.
   */
  public function testCrossHostUsesSso() {
    $domain_a = $this->domainStorage->create([
      'id' => 'site_one',
      'hostname' => 'one.example.com',
      'name' => 'Site One',
      'scheme' => 'https',
      'status' => 1,
      'is_default' => TRUE,
    ]);
    $domain_a->save();

    $domain_b = $this->domainStorage->create([
      'id' => 'site_two',
      'hostname' => 'two.example.com',
      'name' => 'Site Two',
      'scheme' => 'https',
      'status' => 1,
    ]);
    $domain_b->save();

    $request = $this->createRequest(
      'https://one.example.com/admin/domain-sso-switch/site_two',
      'https://one.example.com/admin/content',
    );
    $controller = $this->setupRequest($request);
    $response = $controller->switchDomain('site_two');

    // Different hostname: should go through SSO handshake.
    $target_url = $response->getTargetUrl();
    $this->assertStringContainsString(
      'domain-sso/handshake-issue',
      $target_url,
      'Cross-host domains go through SSO handshake.',
    );
  }

  /**
   * Tests path prefix is stripped from referer in cross-host switch.
   *
   * When switching from a prefixed domain to a different hostname,
   * the source domain's path prefix must be stripped from the
   * referer path so it does not leak into the target URL.
   */
  public function testPrefixStrippedInCrossHostSwitch() {
    $domain_a = $this->domainStorage->create([
      'id' => 'prefixed_site',
      'hostname' => 'one.example.com',
      'name' => 'Prefixed Site',
      'scheme' => 'https',
      'status' => 1,
      'is_default' => TRUE,
      'path_prefix' => 'myprefix',
    ]);
    $domain_a->save();

    $domain_b = $this->domainStorage->create([
      'id' => 'other_site',
      'hostname' => 'two.example.com',
      'name' => 'Other Site',
      'scheme' => 'https',
      'status' => 1,
    ]);
    $domain_b->save();

    // Referer includes the /myprefix/ path prefix.
    $request = $this->createRequest(
      'https://one.example.com/myprefix/admin/domain-sso-switch/other_site',
      'https://one.example.com/myprefix/admin/content',
    );
    $controller = $this->setupRequest($request);
    $response = $controller->switchDomain('other_site');

    // The target parameter in the SSO query should NOT
    // contain the source prefix.
    $redirect_url = $response->getTargetUrl();
    $parsed = parse_url($redirect_url);
    parse_str($parsed['query'] ?? '', $query_params);
    $target_param = urldecode($query_params['target'] ?? '');
    $this->assertStringNotContainsString(
      'myprefix',
      $target_param,
      'Source domain prefix is stripped from cross-host target.',
    );
    $this->assertStringContainsString(
      'admin/content',
      $target_param,
      'Internal path is preserved in cross-host target.',
    );
  }

  /**
   * Tests cross-host switch with prefixes on both domains.
   *
   * When both source and target domains have path prefixes and
   * different hostnames, the source prefix must be stripped and
   * the target prefix applied in the target URL.
   */
  public function testCrossHostBothPrefixed() {
    $domain_a = $this->domainStorage->create([
      'id' => 'site_en',
      'hostname' => 'one.example.com',
      'name' => 'English Site',
      'scheme' => 'https',
      'status' => 1,
      'is_default' => TRUE,
      'path_prefix' => 'en',
    ]);
    $domain_a->save();

    $domain_b = $this->domainStorage->create([
      'id' => 'site_fr',
      'hostname' => 'two.example.com',
      'name' => 'French Site',
      'scheme' => 'https',
      'status' => 1,
      'path_prefix' => 'fr',
    ]);
    $domain_b->save();

    // On domain A (/en/) viewing /en/admin/content, switch to B.
    $request = $this->createRequest(
      'https://one.example.com/en/admin/domain-sso-switch/site_fr',
      'https://one.example.com/en/admin/content',
    );
    $controller = $this->setupRequest($request);
    $response = $controller->switchDomain('site_fr');

    // Extract the target parameter from the SSO issue URL.
    $redirect_url = $response->getTargetUrl();
    $parsed = parse_url($redirect_url);
    parse_str($parsed['query'] ?? '', $query_params);
    $target_param = urldecode($query_params['target'] ?? '');

    // Target should have domain B's prefix, not domain A's.
    $this->assertStringContainsString(
      'two.example.com/fr/admin/content',
      $target_param,
      'Target URL uses the target domain prefix.',
    );
    $this->assertStringNotContainsString(
      '/en/',
      $target_param,
      'Source domain prefix is not in target URL.',
    );
  }

}
