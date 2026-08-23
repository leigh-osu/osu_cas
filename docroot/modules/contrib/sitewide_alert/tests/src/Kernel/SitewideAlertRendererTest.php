<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

use Drupal\Core\Routing\AdminContext;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\sitewide_alert\SitewideAlertRenderer;

/**
 * Tests the SitewideAlertRenderer service.
 *
 * @group sitewide_alert
 * @coversDefaultClass \Drupal\sitewide_alert\SitewideAlertRenderer
 */
final class SitewideAlertRendererTest extends SitewideAlertKernelTestBase {

  /**
   * The sitewide alert renderer service.
   *
   * @var \Drupal\sitewide_alert\SitewideAlertRendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Set up user with permission to view alerts.
    $this->setUpCurrentUser([], ['view published sitewide alert entities']);
    $this->renderer = \Drupal::service('sitewide_alert.sitewide_alert_renderer');
  }

  /**
   * Tests that build returns container even when no alerts exist.
   *
   * @covers ::build
   */
  public function testBuildWithNoAlerts(): void {
    $build = $this->renderer->build();

    // Should return render array with html_tag container (for JS to populate).
    $this->assertIsArray($build);
    $this->assertArrayHasKey('#type', $build);
    $this->assertEquals('html_tag', $build['#type']);
    $this->assertEmpty($build['alerts']);
  }

  /**
   * Tests client-side only rendering (SSR disabled).
   *
   * @covers ::build
   * @covers ::buildClientSideOnly
   */
  public function testBuildClientSideOnly(): void {
    $this->createSiteWideAlert();

    // Ensure SSR is disabled.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', FALSE)->save();

    $build = $this->renderer->build();

    // Should use html_tag type with empty alerts (JS will populate).
    $this->assertArrayHasKey('#type', $build);
    $this->assertEquals('html_tag', $build['#type']);
    $this->assertEquals('div', $build['#tag']);

    // Should have data-sitewide-alert attribute.
    $this->assertArrayHasKey('#attributes', $build);
    $this->assertArrayHasKey('data-sitewide-alert', $build['#attributes']);
    $this->assertEquals('polite', $build['#attributes']['aria-live']);
    $this->assertArrayNotHasKey('role', $build['#attributes']);

    // Alerts should be empty (JS will populate via API).
    $this->assertEmpty($build['alerts']);

    // Should have the JS library attached.
    $this->assertArrayHasKey('#attached', $build);
    $this->assertContains('sitewide_alert/init', $build['#attached']['library']);

    // Should have drupalSettings.
    $this->assertArrayHasKey('drupalSettings', $build['#attached']);
    $this->assertArrayHasKey('sitewideAlert', $build['#attached']['drupalSettings']);
  }

  /**
   * Tests server-side rendering when enabled.
   *
   * @covers ::build
   * @covers ::buildWithServerSideRendering
   */
  public function testBuildWithServerSideRendering(): void {
    $alert = $this->createSiteWideAlert([
      'message' => [
        'value' => 'Test SSR Alert Message',
        'format' => 'plain_text',
      ],
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $build = $this->renderer->build();

    // Should use html_tag type instead of markup.
    $this->assertArrayHasKey('#type', $build);
    $this->assertEquals('html_tag', $build['#type']);
    $this->assertEquals('div', $build['#tag']);

    // Should have data-sitewide-alert attribute.
    $this->assertArrayHasKey('#attributes', $build);
    $this->assertArrayHasKey('data-sitewide-alert', $build['#attributes']);
    $this->assertEquals('polite', $build['#attributes']['aria-live']);
    $this->assertEquals('region', $build['#attributes']['role']);
    $this->assertEquals('Site alerts', (string) $build['#attributes']['aria-label']);

    // Should contain rendered alerts.
    $this->assertArrayHasKey('alerts', $build);
    $this->assertNotEmpty($build['alerts']);

    // Should have the JS library attached.
    $this->assertArrayHasKey('#attached', $build);
    $this->assertContains('sitewide_alert/init', $build['#attached']['library']);
  }

  /**
   * Tests that SSR adds url.path cache context.
   *
   * @covers ::buildWithServerSideRendering
   */
  public function testSsrAddsCacheContext(): void {
    $this->createSiteWideAlert();

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $build = $this->renderer->build();

    // Should have url.path and languages:language_interface cache contexts
    // (in addition to user.permissions).
    $this->assertArrayHasKey('#cache', $build);
    $this->assertArrayHasKey('contexts', $build['#cache']);
    $this->assertContains('url.path', $build['#cache']['contexts']);
    $this->assertContains('languages:language_interface', $build['#cache']['contexts']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);
  }

  /**
   * Tests SSR with path filtering - alert on specific page.
   *
   * @covers ::filterAlertsByPath
   * @covers ::alertShouldShowOnPath
   */
  public function testSsrPathFilteringWithMatchingPath(): void {
    // Create alert that should only show on /test-path.
    $alert = $this->createSiteWideAlert([
      'limit_to_pages' => '/test-path',
      'limit_to_pages_negate' => FALSE,
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    // Mock the current path to /test-path.
    $currentPath = $this->container->get('path.current');
    $currentPath->setPath('/test-path');

    $build = $this->renderer->build();

    // Alert should be rendered since we're on the matching path.
    $this->assertArrayHasKey('#type', $build);
    $this->assertArrayHasKey('alerts', $build);
    $this->assertNotEmpty($build['alerts']);
  }

  /**
   * Tests SSR with path filtering - alert on non-matching path.
   *
   * @covers ::filterAlertsByPath
   * @covers ::alertShouldShowOnPath
   */
  public function testSsrPathFilteringWithNonMatchingPath(): void {
    // Create alert that should only show on /test-path.
    $alert = $this->createSiteWideAlert([
      'limit_to_pages' => '/test-path',
      'limit_to_pages_negate' => FALSE,
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    // Mock the current path to /other-path.
    $currentPath = $this->container->get('path.current');
    $currentPath->setPath('/other-path');

    $build = $this->renderer->build();

    // Should return container with empty alerts when no alerts match path.
    $this->assertArrayHasKey('#type', $build);
    $this->assertEquals('html_tag', $build['#type']);
    $this->assertEmpty($build['alerts']);
  }

  /**
   * Tests SSR with negated path filtering.
   *
   * @covers ::alertShouldShowOnPath
   */
  public function testSsrNegatedPathFiltering(): void {
    // Create alert that should show on all pages EXCEPT /excluded-path.
    $alert = $this->createSiteWideAlert([
      'limit_to_pages' => '/excluded-path',
      'limit_to_pages_negate' => TRUE,
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    // Test on excluded path - should not render alert.
    $currentPath = $this->container->get('path.current');
    $currentPath->setPath('/excluded-path');

    $build = $this->renderer->build();
    // Should return container with empty alerts.
    $this->assertArrayHasKey('#type', $build);
    $this->assertEmpty($build['alerts']);

    // Test on included path - should render alert.
    $currentPath->setPath('/any-other-path');
    // Need to rebuild to get fresh state.
    $build = $this->renderer->build();
    $this->assertArrayHasKey('#type', $build);
    $this->assertArrayHasKey('alerts', $build);
    $this->assertNotEmpty($build['alerts']);
  }

  /**
   * Tests SSR with wildcard path matching.
   *
   * @covers ::alertShouldShowOnPath
   */
  public function testSsrWildcardPathMatching(): void {
    // Create alert that should show on /admin/*.
    $alert = $this->createSiteWideAlert([
      'limit_to_pages' => '/admin/*',
      'limit_to_pages_negate' => FALSE,
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    // Test on matching wildcard path.
    $currentPath = $this->container->get('path.current');
    $currentPath->setPath('/admin/config');

    $build = $this->renderer->build();
    $this->assertArrayHasKey('#type', $build);
    $this->assertArrayHasKey('alerts', $build);
    $this->assertNotEmpty($build['alerts']);
  }

  /**
   * Tests SSR with no page restrictions shows on all pages.
   *
   * @covers ::alertShouldShowOnPath
   */
  public function testSsrNoPageRestrictionsShowsEverywhere(): void {
    // Create alert with no page restrictions.
    $alert = $this->createSiteWideAlert([
      'limit_to_pages' => '',
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    // Test on any path.
    $currentPath = $this->container->get('path.current');
    $currentPath->setPath('/any/random/path');

    $build = $this->renderer->build();
    $this->assertArrayHasKey('#type', $build);
    $this->assertArrayHasKey('alerts', $build);
    $this->assertNotEmpty($build['alerts']);
  }

  /**
   * Tests SSR display order ascending (older first).
   *
   * @covers ::buildWithServerSideRendering
   */
  public function testSsrDisplayOrderAscending(): void {
    $alert1 = $this->createSiteWideAlert(['name' => 'First Alert']);
    sleep(1);
    $alert2 = $this->createSiteWideAlert(['name' => 'Second Alert']);

    // Enable SSR with ascending order (default).
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE);
    $config->set('display_order', 'ascending');
    $config->save();

    $build = $this->renderer->build();

    $this->assertArrayHasKey('alerts', $build);
    $this->assertCount(2, $build['alerts']);
  }

  /**
   * Tests SSR display order descending (newer first).
   *
   * @covers ::buildWithServerSideRendering
   */
  public function testSsrDisplayOrderDescending(): void {
    $alert1 = $this->createSiteWideAlert(['name' => 'First Alert']);
    sleep(1);
    $alert2 = $this->createSiteWideAlert(['name' => 'Second Alert']);

    // Enable SSR with descending order.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE);
    $config->set('display_order', 'descending');
    $config->save();

    $build = $this->renderer->build();

    $this->assertArrayHasKey('alerts', $build);
    $this->assertCount(2, $build['alerts']);
  }

  /**
   * Tests that SSR respects user permissions.
   *
   * @covers ::build
   */
  public function testSsrRespectsPermissions(): void {
    $this->createSiteWideAlert();

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    // Create user without permission.
    $this->setUpCurrentUser([], []);

    $build = $this->renderer->build();

    // Should return empty build (with cache metadata only).
    $this->assertArrayNotHasKey('#type', $build);
    $this->assertArrayNotHasKey('#markup', $build);
  }

  /**
   * Tests that unpublished alerts are not rendered with SSR.
   *
   * @covers ::buildWithServerSideRendering
   */
  public function testSsrDoesNotRenderUnpublishedAlerts(): void {
    // Create an unpublished alert.
    $alert = $this->createSiteWideAlert([
      'status' => 0,
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $build = $this->renderer->build();

    // Should return container but with empty alerts.
    $this->assertArrayHasKey('#type', $build);
    $this->assertEquals('html_tag', $build['#type']);
    $this->assertEmpty($build['alerts']);
  }

  /**
   * Tests that serverSideRender flag is passed in drupalSettings.
   *
   * @covers ::getBuild
   */
  public function testDrupalSettingsIncludesServerSideRenderFlag(): void {
    $this->createSiteWideAlert();

    // Test with SSR disabled.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', FALSE)->save();

    $build = $this->renderer->build();

    $this->assertArrayHasKey('#attached', $build);
    $this->assertArrayHasKey('drupalSettings', $build['#attached']);
    $this->assertArrayHasKey('sitewideAlert', $build['#attached']['drupalSettings']);
    $this->assertFalse($build['#attached']['drupalSettings']['sitewideAlert']['serverSideRender']);

    // Test with SSR enabled.
    $config->set('server_side_render', TRUE)->save();

    $build = $this->renderer->build();

    $this->assertTrue($build['#attached']['drupalSettings']['sitewideAlert']['serverSideRender']);
  }

  /**
   * Tests that SSR sets cache max-age based on scheduled alert changes.
   *
   * @covers ::buildWithServerSideRendering
   */
  public function testSsrCacheMaxAgeWithScheduledAlert(): void {
    // Create a scheduled alert that will expire in 1 hour.
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $build = $this->renderer->build();

    // Should have cache max-age set (approximately 1 hour = 3600 seconds).
    $this->assertArrayHasKey('#cache', $build);
    $this->assertArrayHasKey('max-age', $build['#cache']);
    // Allow some tolerance for test execution time and rounding.
    $this->assertGreaterThan(3500, $build['#cache']['max-age']);
    $this->assertLessThanOrEqual(3605, $build['#cache']['max-age']);
  }

  /**
   * Tests that SSR without scheduled alerts has no max-age restriction.
   *
   * @covers ::buildWithServerSideRendering
   */
  public function testSsrCacheMaxAgeWithoutScheduledAlert(): void {
    // Create a non-scheduled alert.
    $this->createSiteWideAlert([
      'scheduled_alert' => FALSE,
    ]);

    // Enable SSR.
    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $build = $this->renderer->build();

    // Should not have a restrictive max-age (permanent or not set).
    $this->assertArrayHasKey('#cache', $build);
    if (isset($build['#cache']['max-age'])) {
      // -1 means permanent/no restriction in Drupal cache API.
      $this->assertEquals(-1, $build['#cache']['max-age']);
    }
  }

  /**
   * Tests the container has aria-live="polite" for screen reader announcements.
   *
   * @covers ::getBuild
   */
  public function testContainerHasAriaLivePolite(): void {
    $build = $this->renderer->build();

    $this->assertArrayHasKey('#attributes', $build);
    $this->assertEquals('polite', $build['#attributes']['aria-live']);
  }

  /**
   * Tests that the container has no landmark role when there are no alerts.
   *
   * @covers ::build
   * @covers ::buildClientSideOnly
   */
  public function testNoLandmarkRoleWithoutAlerts(): void {
    $build = $this->renderer->build();

    $this->assertArrayNotHasKey('role', $build['#attributes']);
    $this->assertArrayNotHasKey('aria-label', $build['#attributes']);
  }

  /**
   * Tests that SSR container has landmark role when alerts are present.
   *
   * @covers ::buildWithServerSideRendering
   */
  public function testSsrLandmarkRoleWithAlerts(): void {
    $this->createSiteWideAlert();

    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $build = $this->renderer->build();

    $this->assertEquals('region', $build['#attributes']['role']);
    $this->assertEquals('Site alerts', (string) $build['#attributes']['aria-label']);
  }

  /**
   * Tests that SSR container has no landmark role when no alerts match.
   *
   * @covers ::buildWithServerSideRendering
   */
  public function testSsrNoLandmarkRoleWithoutMatchingAlerts(): void {
    $this->createSiteWideAlert([
      'limit_to_pages' => '/some-specific-page',
      'limit_to_pages_negate' => FALSE,
    ]);

    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', TRUE)->save();

    $currentPath = $this->container->get('path.current');
    $currentPath->setPath('/different-page');

    $build = $this->renderer->build();

    $this->assertEmpty($build['alerts']);
    $this->assertArrayNotHasKey('role', $build['#attributes']);
    $this->assertArrayNotHasKey('aria-label', $build['#attributes']);
  }

  /**
   * Tests that client-side only rendering has no landmark role.
   *
   * JS is responsible for adding the role when alerts are loaded.
   *
   * @covers ::buildClientSideOnly
   */
  public function testClientSideNoLandmarkRole(): void {
    $this->createSiteWideAlert();

    $config = \Drupal::configFactory()->getEditable('sitewide_alert.settings');
    $config->set('server_side_render', FALSE)->save();

    $build = $this->renderer->build();

    $this->assertArrayNotHasKey('role', $build['#attributes']);
    $this->assertArrayNotHasKey('aria-label', $build['#attributes']);
  }

  /**
   * Creates a renderer with a mocked admin context.
   *
   * @param bool $isAdminRoute
   *   Whether the admin context should report an admin route.
   *
   * @return \Drupal\sitewide_alert\SitewideAlertRenderer
   *   A renderer instance with the mocked admin context.
   */
  protected function createRendererWithAdminContext(bool $isAdminRoute): SitewideAlertRenderer {
    $adminContext = $this->prophesize(AdminContext::class);
    $adminContext->isAdminRoute()->willReturn($isAdminRoute);

    return new SitewideAlertRenderer(
      $this->container->get('config.factory'),
      $adminContext->reveal(),
      $this->container->get('current_user'),
      $this->container->get('sitewide_alert.sitewide_alert_manager'),
      $this->container->get('entity_type.manager'),
      $this->container->get('path.current'),
      $this->container->get('path.matcher'),
      $this->container->get('datetime.time'),
    );
  }

  /**
   * Tests build returns empty on admin route when show_on_admin is FALSE.
   *
   * @covers ::build
   */
  public function testBuildEmptyOnAdminRouteWhenShowOnAdminFalse(): void {
    $this->createSiteWideAlert();

    \Drupal::configFactory()->getEditable('sitewide_alert.settings')
      ->set('show_on_admin', FALSE)
      ->save();

    $renderer = $this->createRendererWithAdminContext(TRUE);
    $build = $renderer->build();

    // Should return empty build (no #type key) since admin route
    // with show_on_admin=FALSE.
    $this->assertArrayNotHasKey('#type', $build);
  }

  /**
   * Tests build returns content on admin route when show_on_admin is TRUE.
   *
   * @covers ::build
   */
  public function testBuildContentOnAdminRouteWhenShowOnAdminTrue(): void {
    $this->createSiteWideAlert();

    \Drupal::configFactory()->getEditable('sitewide_alert.settings')
      ->set('show_on_admin', TRUE)
      ->save();

    $renderer = $this->createRendererWithAdminContext(TRUE);
    $build = $renderer->build();

    // Should return full build since show_on_admin=TRUE.
    $this->assertArrayHasKey('#type', $build);
    $this->assertEquals('html_tag', $build['#type']);
  }

}
