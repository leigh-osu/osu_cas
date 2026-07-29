<?php

namespace Drupal\Tests\domain_early_negotiation\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the domain early negotiation middleware and service provider.
 *
 * @group domain_early_negotiation
 */
#[Group('domain_early_negotiation')]
#[RunTestsInSeparateProcesses]
class DomainEarlyNegotiationTest extends KernelTestBase {

  use DomainTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_early_negotiation',
    'system',
    'user',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('domain');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'domain', 'domain_early_negotiation']);
  }

  /**
   * Tests that the priority container parameter exists and matches config.
   */
  public function testPriorityContainerParameter(): void {
    $this->assertTrue(
      $this->container->hasParameter('domain_early_negotiation.priority'),
      'The priority parameter should exist in the container.'
    );

    $parameter = $this->container->getParameter('domain_early_negotiation.priority');
    $this->assertIsInt($parameter, 'The priority parameter should be an integer.');

    $config_value = $this->config('domain_early_negotiation.settings')->get('priority');
    $this->assertEquals(
      $config_value,
      $parameter,
      'The container parameter should match the configuration value.'
    );

    $this->assertEquals(220, $parameter, 'The default priority should be 220.');
  }

  /**
   * Tests that the middleware service is registered.
   */
  public function testMiddlewareServiceExists(): void {
    $this->assertTrue(
      $this->container->has('http_middleware.domain_negotiation'),
      'The middleware service should be registered.'
    );

    $middleware = $this->container->get('http_middleware.domain_negotiation');
    $this->assertInstanceOf(
      'Drupal\domain_early_negotiation\StackMiddleware\DomainNegotiationMiddleware',
      $middleware,
      'The middleware should be the correct class.'
    );
  }

  /**
   * Tests that the ConfigSubscriber is registered.
   */
  public function testConfigSubscriberExists(): void {
    $this->assertTrue(
      $this->container->has('Drupal\domain_early_negotiation\EventSubscriber\ConfigSubscriber'),
      'The ConfigSubscriber should be registered as a service.'
    );

    $subscriber = $this->container->get('Drupal\domain_early_negotiation\EventSubscriber\ConfigSubscriber');
    $events = $subscriber::getSubscribedEvents();
    $this->assertArrayHasKey(
      'config.save',
      $events,
      'ConfigSubscriber should subscribe to config.save event.'
    );
  }

  /**
   * Tests that changing priority invalidates the container.
   */
  public function testPriorityChangeInvalidatesContainer(): void {
    $initial = $this->container->getParameter('domain_early_negotiation.priority');
    $this->assertEquals(220, $initial);

    // Change the priority.
    $this->config('domain_early_negotiation.settings')
      ->set('priority', 180)
      ->save();

    // The container should be marked for rebuild.
    $kernel = $this->container->get('kernel');
    $reflection = new \ReflectionProperty($kernel, 'containerNeedsRebuild');
    $reflection->setAccessible(TRUE);
    $this->assertTrue(
      $reflection->getValue($kernel),
      'Container should be marked for rebuild after priority change.'
    );

    // Rebuild and verify the new parameter value.
    $kernel->rebuildContainer();
    $updated = $this->container->getParameter('domain_early_negotiation.priority');
    $this->assertEquals(
      180,
      $updated,
      'After rebuild, the priority parameter should reflect the new value.'
    );
  }

  /**
   * Tests the middleware negotiates the active domain.
   */
  public function testMiddlewareNegotiatesDomain(): void {
    $this->domainCreateTestDomains(2);
    $domains = $this->getDomains();
    $default = reset($domains);

    // Set the request hostname to match the default domain.
    $request = \Drupal::request();
    $request->headers->set('HOST', $default->getHostname());

    $middleware = $this->container->get('http_middleware.domain_negotiation');

    // Create a mock inner kernel.
    $inner_kernel = $this->createMock(HttpKernelInterface::class);
    $inner_kernel->method('handle')
      ->willReturn(new Response());

    // Use reflection to set the inner kernel.
    $reflection = new \ReflectionProperty($middleware, 'httpKernel');
    $reflection->setAccessible(TRUE);
    $reflection->setValue($middleware, $inner_kernel);

    // Handle the request through the middleware.
    $response = $middleware->handle($request);

    // After middleware runs, the domain should be negotiated.
    $negotiator = $this->container->get('domain.negotiator');
    $active = $negotiator->getActiveDomain();
    $this->assertNotNull($active, 'An active domain should be negotiated.');
    $this->assertEquals(
      $default->id(),
      $active->id(),
      'The negotiated domain should match the request hostname.'
    );
  }

}
