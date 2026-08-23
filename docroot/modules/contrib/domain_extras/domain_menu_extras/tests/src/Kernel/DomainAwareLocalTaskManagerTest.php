<?php

namespace Drupal\Tests\domain_menu_extras\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\domain\Entity\Domain;
use Drupal\domain_menu_extras\Menu\DomainAwareLocalTaskManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests that the local task manager cache key varies by active domain.
 *
 * Drupal core keys LocalTaskManager's discovery cache on language only,
 * so derivers reading overridable config (e.g. ThemeLocalTask reading
 * system.theme.default) freeze their result on the first domain that
 * populates the cache. DomainMenuExtrasServiceProvider::alter() swaps
 * in DomainAwareLocalTaskManager, which appends the active domain id
 * to the cache key. This test covers two things:
 *  1. The class swap via setClass()/addArgument() takes effect — the
 *     service container returns our subclass.
 *  2. The cache key actually includes the active domain id and changes
 *     when the active domain changes (a fresh service instance is built
 *     with the new domain context).
 *
 * @group domain_menu_extras
 *
 * @see \Drupal\domain_menu_extras\Menu\DomainAwareLocalTaskManager
 * @see https://www.drupal.org/project/domain_extras/issues/3588108
 */
#[Group('domain_menu_extras')]
#[RunTestsInSeparateProcesses]
class DomainAwareLocalTaskManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_menu_extras',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('domain');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    Domain::create([
      'id' => 'a_example_com',
      'hostname' => 'a.example.com',
      'name' => 'A',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
    ])->save();
    Domain::create([
      'id' => 'b_example_com',
      'hostname' => 'b.example.com',
      'name' => 'B',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
    ])->save();
  }

  /**
   * The active domain id is part of the local task plugin cache key.
   *
   * Asserts both (a) the alter() in DomainMenuExtrasServiceProvider
   * successfully swaps the class on the existing service definition
   * and (b) the cache key built in the constructor includes the active
   * domain id resolved at instantiation time. Re-instantiating the
   * service with a different active domain produces a different cache
   * key, which is what allows derivers reading overridable config to
   * keep their results separate per domain instead of leaking across
   * them.
   */
  public function testCacheKeyIncludesActiveDomainId(): void {
    // Push a request matching domain A's hostname and force negotiation.
    $this->pushRequest('http://a.example.com/');
    $this->container->get('domain.negotiator')->getActiveDomain(TRUE);

    $manager = $this->container->get('plugin.manager.menu.local_task');
    self::assertInstanceOf(
      DomainAwareLocalTaskManager::class,
      $manager,
      'DomainMenuExtrasServiceProvider::alter() swapped the local task manager class.'
    );
    self::assertSame(
      'local_task_plugins:en:a_example_com',
      $this->readCacheKey($manager),
      'Cache key includes the active domain id resolved at construction time.'
    );

    // Switch the active domain. The service is already instantiated, so
    // its cache key is frozen at construction time — bind the new
    // domain, drop the cached service to force a fresh instance, and
    // re-read the key.
    $this->pushRequest('http://b.example.com/');
    $this->container->get('domain.negotiator')->getActiveDomain(TRUE);
    $this->container->set('plugin.manager.menu.local_task', NULL);

    $manager = $this->container->get('plugin.manager.menu.local_task');
    self::assertSame(
      'local_task_plugins:en:b_example_com',
      $this->readCacheKey($manager),
      'A second instantiation with a different active domain yields a different cache key.'
    );
  }

  /**
   * The cache key falls back to 'und' when no domain is bound.
   *
   * Covers the CLI / install-hook / non-bound scenario:
   * DomainNegotiationContext::getDomainId() returns NULL because no
   * matching request has been pushed and no negotiation has been
   * forced. The constructor's `?? 'und'` fallback keeps the cache
   * key stable across those contexts so they share a single slot
   * rather than thrashing — without it, every CLI invocation would
   * either fatal on the NULL or scatter the cache by whatever
   * value the missing-domain code path produces.
   */
  public function testCacheKeyFallsBackToUndWhenNoDomainIsBound(): void {
    // No pushRequest() and no negotiator->getActiveDomain() call.
    // KernelTestBase's default synthetic request does not match any
    // of the test domain hostnames, so DomainNegotiationContext stays
    // unbound for the lifetime of this test.
    self::assertNull(
      $this->container->get('domain.negotiation_context')->getDomainId(),
      'Sanity: no domain has been negotiated.',
    );

    $manager = $this->container->get('plugin.manager.menu.local_task');
    self::assertInstanceOf(
      DomainAwareLocalTaskManager::class,
      $manager,
      'DomainMenuExtrasServiceProvider::alter() swapped the local task manager class.',
    );
    self::assertSame(
      'local_task_plugins:en:und',
      $this->readCacheKey($manager),
      "Cache key uses the 'und' sentinel when no domain has been negotiated.",
    );
  }

  /**
   * Pushes a request matching one of the test domain hostnames.
   *
   * A mock session is attached because some kernel.request subscribers
   * read the session and KernelTestBase does not provide one on the
   * synthetic Request it pushes during setup.
   */
  private function pushRequest(string $url): void {
    $request = Request::create($url);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Reads the cacheKey property up the class hierarchy.
   *
   * The property is declared on a parent class of DefaultPluginManager,
   * so a direct ReflectionProperty on the concrete class is not enough
   * — walk the inheritance chain to find the declaring class.
   */
  private function readCacheKey(object $manager): string {
    $rc = new \ReflectionClass($manager);
    while ($rc) {
      if ($rc->hasProperty('cacheKey')) {
        return (string) $rc->getProperty('cacheKey')->getValue($manager);
      }
      $rc = $rc->getParentClass();
    }
    self::fail('Plugin manager does not expose a cacheKey property.');
  }

}
