<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\sitewide_alert\Traits\SitewideAlertTestTrait;

/**
 * Tests the sitewide_alert_block submodule.
 *
 * @group sitewide_alert
 */
final class SitewideAlertBlockTest extends BrowserTestBase {

  use SitewideAlertTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sitewide_alert',
    'sitewide_alert_block',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the sitewide alert block in the content region.
    $this->drupalPlaceBlock('sitewide_alert_block', [
      'region' => 'content',
    ]);

    $this->drupalLogin($this->createUser([
      'view published sitewide alert entities',
    ]));
  }

  /**
   * Tests block renders the alert container.
   */
  public function testBlockRendersAlerts(): void {
    $this->createSiteWideAlert();

    $this->drupalGet('<front>');
    $this->assertSession()->elementExists('css', '[data-sitewide-alert]');
  }

  /**
   * Tests only one data-sitewide-alert container exists on page.
   *
   * When the block submodule is enabled, page_top should be skipped,
   * so there should be only one container from the block.
   */
  public function testNoDuplicateContainers(): void {
    $this->createSiteWideAlert();

    $this->drupalGet('<front>');
    $elements = $this->cssSelect('[data-sitewide-alert]');
    $this->assertCount(1, $elements, 'Only one [data-sitewide-alert] container should exist on page.');
  }

}
