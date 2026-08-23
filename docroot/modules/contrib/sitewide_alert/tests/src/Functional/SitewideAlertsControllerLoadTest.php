<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Functional;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\sitewide_alert\Traits\SitewideAlertTestTrait;

/**
 * Tests the /sitewide_alert/load JSON endpoint.
 *
 * @group sitewide_alert
 * @coversDefaultClass \Drupal\sitewide_alert\Controller\SitewideAlertsController
 */
final class SitewideAlertsControllerLoadTest extends BrowserTestBase {

  use SitewideAlertTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sitewide_alert'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->createUser(['view published sitewide alert entities']));
  }

  /**
   * Fetches the JSON response from the load endpoint.
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
   * Tests response contains expected keys for each alert.
   *
   * @covers ::load
   */
  public function testResponseContainsExpectedKeys(): void {
    $this->createSiteWideAlert();
    $data = $this->fetchAlerts();

    $this->assertArrayHasKey('sitewideAlerts', $data);
    $this->assertCount(1, $data['sitewideAlerts']);

    $alert = $data['sitewideAlerts'][0];
    $expectedKeys = [
      'uuid',
      'dismissible',
      'dismissalIgnoreBefore',
      'styleClass',
      'showOnPages',
      'negateShowOnPages',
      'renderedAlert',
      'changed',
    ];
    foreach ($expectedKeys as $key) {
      $this->assertArrayHasKey($key, $alert, "Missing expected key: $key");
    }
  }

  /**
   * Tests that unpublished alerts are excluded from the response.
   *
   * @covers ::load
   */
  public function testExcludesUnpublishedAlerts(): void {
    $this->createSiteWideAlert(['status' => 1]);
    $this->createSiteWideAlert(['status' => 0]);

    $data = $this->fetchAlerts();
    $this->assertCount(1, $data['sitewideAlerts']);
  }

  /**
   * Tests that future scheduled alerts are excluded from the response.
   *
   * @covers ::load
   */
  public function testExcludesFutureScheduledAlerts(): void {
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
   * Tests descending display order reverses alert order.
   *
   * @covers ::load
   */
  public function testDisplayOrderDescending(): void {
    $alert1 = $this->createSiteWideAlert(['name' => 'First Alert']);
    sleep(1);
    $alert2 = $this->createSiteWideAlert(['name' => 'Second Alert']);

    \Drupal::configFactory()->getEditable('sitewide_alert.settings')
      ->set('display_order', 'descending')
      ->save();

    $data = $this->fetchAlerts();
    $this->assertCount(2, $data['sitewideAlerts']);

    // Descending should have the newer alert (higher ID) first.
    $this->assertEquals($alert2->uuid(), $data['sitewideAlerts'][0]['uuid']);
    $this->assertEquals($alert1->uuid(), $data['sitewideAlerts'][1]['uuid']);
  }

  /**
   * Tests page visibility data is populated correctly.
   *
   * @covers ::load
   */
  public function testPageVisibilityData(): void {
    $this->createSiteWideAlert([
      'limit_to_pages' => "/test-page\n/another-page",
      'limit_to_pages_negate' => TRUE,
    ]);

    $data = $this->fetchAlerts();
    $alert = $data['sitewideAlerts'][0];

    $this->assertNotEmpty($alert['showOnPages']);
    $this->assertTrue($alert['negateShowOnPages']);
  }

  /**
   * Tests dismissible data is correct.
   *
   * @covers ::load
   */
  public function testDismissibleData(): void {
    $this->createSiteWideAlert([
      'dismissible' => TRUE,
      'dismissible_ignore_before_time' => 1234567890,
    ]);

    $data = $this->fetchAlerts();
    $alert = $data['sitewideAlerts'][0];

    $this->assertTrue($alert['dismissible']);
    $this->assertEquals(1234567890, $alert['dismissalIgnoreBefore']);
  }

  /**
   * Tests response has sitewide_alert_list cache tag.
   *
   * @covers ::load
   */
  public function testResponseCacheTags(): void {
    $this->createSiteWideAlert();
    $this->drupalGet('/sitewide_alert/load');

    $cacheTagHeader = $this->getSession()->getResponseHeader('X-Drupal-Cache-Tags');
    $this->assertNotNull($cacheTagHeader);
    $this->assertStringContainsString('sitewide_alert_list', $cacheTagHeader);
  }

}
