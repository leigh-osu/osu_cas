<?php

namespace Drupal\Tests\domain_render_context\Kernel;

use Drupal\Core\Routing\RequestContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\domain\DomainNegotiationContext;
use Drupal\domain\Entity\Domain;
use Drupal\domain_render_context\DomainRenderContextInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests rendering in the context of another domain.
 *
 * Covers the three things the service switches (the domain negotiation
 * context, which per-domain configuration overrides and the path prefix read;
 * the router request context, which the host of an absolute URL comes from;
 * and the active theme), and the restore contract in every shape a caller can
 * hit: normal return, an exception, nested calls, an unknown domain and a run
 * that started with nothing negotiated, as cron does.
 *
 * @group domain_render_context
 */
#[Group('domain_render_context')]
#[RunTestsInSeparateProcesses]
class DomainRenderContextTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    // Domain Configuration depends on the language module, and a kernel test
    // does not resolve module dependencies, so list it here: without it the
    // language manager decorator is built from core's one-argument
    // language_manager definition and the container fails.
    'language',
    'domain',
    'domain_config',
    'domain_render_context',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('domain');
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'language']);

    // Two domains may share a hostname only in path prefix mode, which the
    // prefix test below needs anyway.
    $this->config('domain.settings')->set('path_prefix', TRUE)->save();
    $this->container = $this->container->get('kernel')->rebuildContainer();

    Domain::create([
      'id' => 'example_com',
      'hostname' => 'example.com',
      'name' => 'Example',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
    ])->save();
    Domain::create([
      'id' => 'other_example_com',
      'hostname' => 'other.example.com:8080',
      'name' => 'Other',
      'scheme' => 'https',
      'status' => 1,
      'weight' => 1,
    ])->save();
    Domain::create([
      'id' => 'shop_example_com',
      'hostname' => 'example.com',
      'path_prefix' => 'shop',
      'name' => 'Shop',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 2,
    ])->save();

    $this->config('system.site')->set('name', 'Base name')->save();
    $this->container->get('domain.config_factory_override')
      ->getOverrideEditable('other_example_com', 'system.site')
      ->set('name', 'Other name')
      ->save();

    $this->container->get('theme_installer')->install(['stark', 'olivero']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $this->container->get('domain.config_factory_override')
      ->getOverrideEditable('other_example_com', 'system.theme')
      ->set('default', 'olivero')
      ->save();
  }

  /**
   * Returns the service under test.
   *
   * @return \Drupal\domain_render_context\DomainRenderContextInterface
   *   The domain render context.
   */
  protected function renderContext(): DomainRenderContextInterface {
    return $this->container->get(DomainRenderContextInterface::class);
  }

  /**
   * Returns the domain negotiation context.
   *
   * @return \Drupal\domain\DomainNegotiationContext
   *   The negotiation context.
   */
  protected function negotiationContext(): DomainNegotiationContext {
    return $this->container->get('domain.negotiation_context');
  }

  /**
   * Returns the router request context.
   *
   * @return \Drupal\Core\Routing\RequestContext
   *   The request context.
   */
  protected function requestContext(): RequestContext {
    return $this->container->get('router.request_context');
  }

  /**
   * The active domain, its configuration and the request origin all switch.
   */
  public function testSwitchesConfigurationAndRequestOrigin(): void {
    $host_before = $this->requestContext()->getHost();
    $this->assertSame('Base name', $this->config('system.site')->get('name'));

    $inside = $this->renderContext()->inDomain('other_example_com', function (): array {
      return [
        'domain' => $this->negotiationContext()->getDomainId(),
        'site_name' => $this->container->get('config.factory')->get('system.site')->get('name'),
        'scheme' => $this->requestContext()->getScheme(),
        'host' => $this->requestContext()->getHost(),
        'https_port' => $this->requestContext()->getHttpsPort(),
        'http_port' => $this->requestContext()->getHttpPort(),
      ];
    });

    $this->assertSame('other_example_com', $inside['domain']);
    $this->assertSame('Other name', $inside['site_name'], 'The per-domain configuration override of the render domain applies.');
    $this->assertSame('https', $inside['scheme']);
    $this->assertSame('other.example.com', $inside['host']);
    $this->assertSame(8080, $inside['https_port'], 'An explicit port in the hostname reaches the request context.');
    $this->assertSame(80, $inside['http_port'], 'The unused scheme keeps its default port.');

    $this->assertSame($host_before, $this->requestContext()->getHost());
    $this->assertSame('http', $this->requestContext()->getScheme());
    $this->assertSame(443, $this->requestContext()->getHttpsPort());
    $this->assertSame(
      'Base name',
      $this->container->get('config.factory')->get('system.site')->get('name'),
      'The render domain override no longer applies once the block is left.'
    );
  }

  /**
   * The render domain's own theme becomes the active one.
   *
   * A multi-domain site routinely gives each domain its own theme, through a
   * per-domain system.theme override. Without this, an email or a PDF built
   * while another domain is active renders with that domain's templates,
   * assets and logo.
   */
  public function testSwitchesTheActiveTheme(): void {
    $theme_manager = $this->container->get('theme.manager');
    $this->assertFalse($theme_manager->hasActiveTheme(), 'No theme is initialized before the call.');

    $inside = $this->renderContext()->inDomain('other_example_com', static fn () => $theme_manager->getActiveTheme()->getName());

    $this->assertSame('olivero', $inside, 'The render domain theme override wins over the base default theme.');
    $this->assertFalse(
      $theme_manager->hasActiveTheme(),
      'A theme initialized only for the switch does not stay active afterwards.'
    );
  }

  /**
   * An already active theme is put back exactly as it was.
   */
  public function testRestoresThePreviouslyActiveTheme(): void {
    $theme_manager = $this->container->get('theme.manager');
    $theme_manager->setActiveTheme($this->container->get('theme.initialization')->initTheme('stark'));

    $inside = $this->renderContext()->inDomain('other_example_com', static fn () => $theme_manager->getActiveTheme()->getName());

    $this->assertSame('olivero', $inside);
    $this->assertSame('stark', $theme_manager->getActiveTheme()->getName());
  }

  /**
   * The callback's return value is handed back to the caller.
   */
  public function testReturnsTheCallbackResult(): void {
    $this->assertSame(42, $this->renderContext()->inDomain('other_example_com', fn () => 42));
  }

  /**
   * The domain path prefix follows the switch, with no per-URL option.
   */
  public function testPathPrefixFollowsTheSwitch(): void {
    $processor = $this->container->get('domain.prefix_path_processor');

    $options = [];
    $processor->processOutbound('/node/1', $options);
    $this->assertArrayNotHasKey('prefix', $options, 'No prefix applies outside the block.');

    $inside = $this->renderContext()->inDomain('shop_example_com', static function () use ($processor): array {
      $options = [];
      $processor->processOutbound('/node/1', $options);
      return $options;
    });
    $this->assertSame('shop/', $inside['prefix'] ?? NULL);
  }

  /**
   * The previous context is restored even when the callback throws.
   */
  public function testRestoresAfterAnException(): void {
    $host_before = $this->requestContext()->getHost();

    try {
      $this->renderContext()->inDomain('other_example_com', static function (): void {
        throw new \RuntimeException('Rendering failed.');
      });
      $this->fail('The exception should have been rethrown.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame('Rendering failed.', $e->getMessage());
    }

    $this->assertSame($host_before, $this->requestContext()->getHost());
    $this->assertSame('http', $this->requestContext()->getScheme());
    $this->assertSame(
      'Base name',
      $this->container->get('config.factory')->get('system.site')->get('name')
    );
  }

  /**
   * Nested switches restore the intermediate context, then the original one.
   */
  public function testNestedSwitchesRestoreInOrder(): void {
    $host_before = $this->requestContext()->getHost();

    $seen = $this->renderContext()->inDomain('other_example_com', function (): array {
      $inner = $this->renderContext()->inDomain('shop_example_com', fn () => $this->negotiationContext()->getDomainId());
      return [
        'inner' => $inner,
        'after_inner' => $this->negotiationContext()->getDomainId(),
        'after_inner_host' => $this->requestContext()->getHost(),
      ];
    });

    $this->assertSame('shop_example_com', $seen['inner']);
    $this->assertSame('other_example_com', $seen['after_inner'], 'Leaving the inner block returns to the outer domain.');
    $this->assertSame('other.example.com', $seen['after_inner_host']);
    $this->assertSame($host_before, $this->requestContext()->getHost());
  }

  /**
   * An unknown domain runs the callback in the unchanged context.
   */
  public function testUnknownDomainKeepsTheCurrentContext(): void {
    $host_before = $this->requestContext()->getHost();

    $ran = $this->renderContext()->inDomain('deleted_example_com', function (): array {
      return [
        'host' => $this->requestContext()->getHost(),
        'site_name' => $this->container->get('config.factory')->get('system.site')->get('name'),
      ];
    });

    $this->assertSame($host_before, $ran['host']);
    $this->assertSame('Base name', $ran['site_name']);
  }

  /**
   * A run that starts with nothing negotiated does not keep the render domain.
   *
   * The state cron and the CLI start in: the negotiation context holds no
   * domain at all. Since it cannot be emptied again, the restore re-negotiates
   * so that what follows is not silently served the render domain's
   * configuration overrides.
   */
  public function testRestoreWhenNothingWasNegotiated(): void {
    $this->assertFalse($this->negotiationContext()->hasDomain(), 'Nothing is negotiated before the call.');

    $this->renderContext()->inDomain('other_example_com', static fn () => NULL);

    $this->assertNotSame(
      'other_example_com',
      $this->negotiationContext()->getDomainId(),
      'The render domain is not left behind as the active domain.'
    );
    $this->assertSame(
      'Base name',
      $this->container->get('config.factory')->get('system.site')->get('name'),
      'Configuration reads are back to base once the block is left.'
    );
  }

  /**
   * The restore closure of enter() is safe to call more than once.
   */
  public function testRestoreClosureIsIdempotent(): void {
    $restore = $this->renderContext()->enter('other_example_com');
    $this->assertSame('other.example.com', $this->requestContext()->getHost());

    $restore();
    $host_after = $this->requestContext()->getHost();
    $restore();

    $this->assertSame($host_after, $this->requestContext()->getHost());
  }

}
