<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Functional;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\sitewide_alert\Traits\SitewideAlertTestTrait;
use Drupal\user\RoleInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests HTTP caching headers and JSON response data on /sitewide_alert/load.
 *
 * This test class covers two concerns:
 * - Cache-Control headers: Verifies that the endpoint returns proper directives
 *   so reverse proxies (Varnish, CDN, Nginx) can cache the response. These
 *   tests use a bare Guzzle HTTP client (via anonymousGet()) because
 *   BrowserTestBase's Mink session always sends a session cookie, even when no
 *   user is logged in. Drupal's page_cache middleware sees that cookie, treats
 *   the request as non-anonymous, and responds with "Cache-Control: private".
 *   A cookieless client mirrors how a real reverse proxy or CDN would hit the
 *   endpoint.
 * - Response freshness: Verifies that entity changes (create, delete, publish,
 *   unpublish) are reflected in the endpoint. These tests use drupalGet() with
 *   an authenticated session, which bypasses page_cache and hits the controller
 *   directly on every request.
 *
 * @group sitewide_alert
 * @coversDefaultClass \Drupal\sitewide_alert\Controller\SitewideAlertsController
 */
final class SitewideAlertsControllerCacheTest extends BrowserTestBase {

  use SitewideAlertTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sitewide_alert', 'page_cache'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, [
      'view published sitewide alert entities',
    ]);

    // Enable page caching. The controller triggers the kill switch when this
    // is 0, which would prevent caching headers from being set.
    $this->config('system.performance')
      ->set('cache.page.max_age', 300)
      ->save();
  }

  /**
   * Makes a cookieless GET request to simulate a truly anonymous visitor.
   *
   * BrowserTestBase's Mink session always carries cookies, which causes
   * Drupal to mark responses as private. Using a bare HTTP client avoids
   * this and mirrors how reverse proxies see the endpoint.
   *
   * @param string $path
   *   The path to request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response.
   */
  protected function anonymousGet(string $path): ResponseInterface {
    $url = $this->getAbsoluteUrl($path);
    return $this->getHttpClient()->get($url, ['http_errors' => FALSE]);
  }

  /**
   * Fetches alerts via an authenticated session (bypasses page_cache).
   *
   * Uses drupalGet() which always carries a session cookie, causing Drupal's
   * page_cache to skip caching. This ensures the controller is hit directly
   * on every request, making it reliable for testing that entity changes are
   * immediately reflected in the response.
   *
   * @return array
   *   The decoded JSON response data.
   */
  protected function fetchAlerts(): array {
    $this->drupalGet('/sitewide_alert/load');
    $content = $this->getSession()->getPage()->getContent();
    return json_decode($content, TRUE);
  }

  /**
   * Returns the Cache-Control header from the load endpoint.
   *
   * @return string
   *   The Cache-Control header value.
   */
  protected function getCacheControlHeader(): string {
    $this->createSiteWideAlert();
    $response = $this->anonymousGet('/sitewide_alert/load');
    return $response->getHeaderLine('Cache-Control');
  }

  /**
   * Tests that the response is publicly cacheable.
   *
   * Reverse proxies require the response to be marked as public. Drupal's
   * page_cache module sets this for anonymous requests.
   *
   * @covers ::load
   */
  public function testResponseIsPubliclyCacheable(): void {
    $cacheControl = $this->getCacheControlHeader();

    $this->assertStringContainsString('public', $cacheControl);
    $this->assertStringNotContainsString('private', $cacheControl);
    $this->assertStringNotContainsString('no-cache', $cacheControl);
    $this->assertStringNotContainsString('no-store', $cacheControl);
  }

  /**
   * Tests that Cache-Control contains the default max-age value.
   *
   * The default cache_max_age config value is 15 seconds.
   *
   * @covers ::load
   */
  public function testCacheControlContainsMaxAge(): void {
    $cacheControl = $this->getCacheControlHeader();

    $this->assertMatchesRegularExpression('/\bmax-age=15\b/', $cacheControl);
  }

  /**
   * Tests that Cache-Control contains s-maxage for reverse proxies.
   *
   * The s-maxage directive is what Varnish, CDNs, and other reverse proxies
   * use to determine how long to cache a response.
   *
   * @covers ::load
   */
  public function testCacheControlContainsSharedMaxAge(): void {
    $cacheControl = $this->getCacheControlHeader();

    $this->assertMatchesRegularExpression('/\bs-maxage=15\b/', $cacheControl);
  }

  /**
   * Tests that custom cache_max_age config is reflected in headers.
   *
   * @covers ::load
   */
  public function testCustomCacheMaxAgeReflectedInHeaders(): void {
    $this->config('sitewide_alert.settings')
      ->set('cache_max_age', 45)
      ->save();

    $cacheControl = $this->getCacheControlHeader();

    $this->assertMatchesRegularExpression('/\bmax-age=45\b/', $cacheControl);
    $this->assertMatchesRegularExpression('/\bs-maxage=45\b/', $cacheControl);
  }

  /**
   * Tests that the response Content-Type is JSON.
   *
   * @covers ::load
   */
  public function testResponseContentTypeIsJson(): void {
    $this->createSiteWideAlert();
    $response = $this->anonymousGet('/sitewide_alert/load');

    $contentType = $response->getHeaderLine('Content-Type');
    $this->assertStringContainsString('application/json', $contentType);
  }

  /**
   * Tests that a newly created alert appears in the endpoint.
   *
   * @covers ::load
   */
  public function testNewAlertAppearsInResponse(): void {
    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
    ]));

    // Endpoint starts empty.
    $data = $this->fetchAlerts();
    $this->assertCount(0, $data['sitewideAlerts']);

    // Create an alert and verify it appears.
    $alert = $this->createSiteWideAlert();
    $data = $this->fetchAlerts();
    $this->assertCount(1, $data['sitewideAlerts']);
    $this->assertEquals($alert->uuid(), $data['sitewideAlerts'][0]['uuid']);

    // Create a second alert and verify both appear.
    $alert2 = $this->createSiteWideAlert();
    $data = $this->fetchAlerts();
    $this->assertCount(2, $data['sitewideAlerts']);

    $uuids = array_column($data['sitewideAlerts'], 'uuid');
    $this->assertContains($alert->uuid(), $uuids);
    $this->assertContains($alert2->uuid(), $uuids);
  }

  /**
   * Tests that a deleted alert disappears from the endpoint.
   *
   * @covers ::load
   */
  public function testDeletedAlertDisappearsFromResponse(): void {
    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
    ]));

    $alert1 = $this->createSiteWideAlert();
    $alert2 = $this->createSiteWideAlert();

    $data = $this->fetchAlerts();
    $this->assertCount(2, $data['sitewideAlerts']);

    // Delete the first alert.
    $alert1->delete();

    $data = $this->fetchAlerts();
    $this->assertCount(1, $data['sitewideAlerts']);
    $this->assertEquals($alert2->uuid(), $data['sitewideAlerts'][0]['uuid']);

    // Delete the second alert.
    $alert2->delete();

    $data = $this->fetchAlerts();
    $this->assertCount(0, $data['sitewideAlerts']);
  }

  /**
   * Tests that unpublishing an alert removes it from the endpoint.
   *
   * Note: Drupal's EntityPublishedInterface::setPublished() takes no arguments
   * and always sets the entity to published. Use setUnpublished() instead.
   *
   * @covers ::load
   */
  public function testUnpublishedAlertDisappearsFromResponse(): void {
    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
    ]));

    $alert = $this->createSiteWideAlert(['status' => 1]);

    $data = $this->fetchAlerts();
    $this->assertCount(1, $data['sitewideAlerts']);

    // Unpublish the alert.
    $alert->setUnpublished();
    $alert->save();

    $data = $this->fetchAlerts();
    $this->assertCount(0, $data['sitewideAlerts']);
  }

  /**
   * Tests that republishing an alert makes it reappear in the endpoint.
   *
   * @covers ::load
   */
  public function testRepublishedAlertReappearsInResponse(): void {
    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
    ]));

    $alert = $this->createSiteWideAlert(['status' => 0]);

    $data = $this->fetchAlerts();
    $this->assertCount(0, $data['sitewideAlerts']);

    // Publish the alert.
    $alert->setPublished();
    $alert->save();

    $data = $this->fetchAlerts();
    $this->assertCount(1, $data['sitewideAlerts']);
    $this->assertEquals($alert->uuid(), $data['sitewideAlerts'][0]['uuid']);
  }

  /**
   * Tests that a scheduled alert within its time window appears in response.
   *
   * A scheduled alert must be active (status=1), have scheduled_alert=TRUE,
   * and the current time must fall within the scheduled date range.
   *
   * @covers ::load
   */
  public function testScheduledAlertWithinWindowAppearsInResponse(): void {
    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
    ]));

    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $alert = $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $data = $this->fetchAlerts();
    $this->assertCount(1, $data['sitewideAlerts']);
    $this->assertEquals($alert->uuid(), $data['sitewideAlerts'][0]['uuid']);
  }

  /**
   * Tests that a future scheduled alert does not appear in response.
   *
   * @covers ::load
   */
  public function testFutureScheduledAlertExcludedFromResponse(): void {
    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
    ]));

    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('+1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+2 hours')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $data = $this->fetchAlerts();
    $this->assertCount(0, $data['sitewideAlerts']);
  }

  /**
   * Tests that an expired scheduled alert does not appear in response.
   *
   * @covers ::load
   */
  public function testExpiredScheduledAlertExcludedFromResponse(): void {
    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
    ]));

    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-2 hours')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $data = $this->fetchAlerts();
    $this->assertCount(0, $data['sitewideAlerts']);
  }

  /**
   * Tests that only the in-window scheduled alert appears among mixed alerts.
   *
   * Creates three alerts: one currently active scheduled alert, one future
   * scheduled alert, and one non-scheduled (always visible) alert. Verifies
   * that the future scheduled alert is excluded while the other two appear.
   *
   * @covers ::load
   */
  public function testMixedScheduledAndUnscheduledAlerts(): void {
    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
    ]));

    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    // Non-scheduled alert: always visible.
    $nonScheduled = $this->createSiteWideAlert();

    // Scheduled alert within its window: visible.
    $activeScheduled = $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    // Scheduled alert in the future: not visible.
    $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('+1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+2 hours')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $data = $this->fetchAlerts();
    $this->assertCount(2, $data['sitewideAlerts']);

    $uuids = array_column($data['sitewideAlerts'], 'uuid');
    $this->assertContains($nonScheduled->uuid(), $uuids);
    $this->assertContains($activeScheduled->uuid(), $uuids);
  }

  /**
   * Tests that an inactive scheduled alert does not appear in response.
   *
   * A scheduled alert must also be published (status=1) to be visible.
   * An unpublished alert with valid scheduling dates should not appear.
   *
   * @covers ::load
   */
  public function testInactiveScheduledAlertExcludedFromResponse(): void {
    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
    ]));

    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $this->createSiteWideAlert([
      'status' => 0,
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $data = $this->fetchAlerts();
    $this->assertCount(0, $data['sitewideAlerts']);
  }

}
