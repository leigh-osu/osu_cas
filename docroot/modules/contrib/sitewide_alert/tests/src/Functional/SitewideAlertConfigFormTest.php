<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Functional;

use Drupal\sitewide_alert\AlertStyleProvider;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the SitewideAlertConfigForm.
 *
 * @group sitewide_alert
 * @coversDefaultClass \Drupal\sitewide_alert\Form\SitewideAlertConfigForm
 */
final class SitewideAlertConfigFormTest extends BrowserTestBase {

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
    $this->drupalLogin($this->createUser(['administer sitewide alert']));
  }

  /**
   * Tests all settings save correctly.
   *
   * @covers ::submitForm
   */
  public function testAllSettingsSaveCorrectly(): void {
    $edit = [
      'show_on_admin' => TRUE,
      'alert_styles' => "info|Information\nwarning|Warning",
      'show_count' => TRUE,
      'display_order' => 'descending',
      'automatic_refresh' => TRUE,
      'refresh_interval' => 30,
      'cache_max_age' => 60,
      'server_side_render' => TRUE,
      'show_untranslated' => TRUE,
    ];

    $this->drupalGet('/admin/config/sitewide_alerts');
    $this->submitForm($edit, 'Save configuration');

    $config = \Drupal::config('sitewide_alert.settings');
    $this->assertEquals(TRUE, $config->get('show_on_admin'));
    $this->assertStringContainsString('info|Information', $config->get('alert_styles'));
    $this->assertStringContainsString('warning|Warning', $config->get('alert_styles'));
    $this->assertEquals(TRUE, $config->get('show_count'));
    $this->assertEquals('descending', $config->get('display_order'));
    $this->assertEquals(TRUE, $config->get('automatic_refresh'));
    $this->assertEquals(30, $config->get('refresh_interval'));
    $this->assertEquals(60, $config->get('cache_max_age'));
    $this->assertEquals(TRUE, $config->get('server_side_render'));
    $this->assertEquals(TRUE, $config->get('show_untranslated'));
  }

  /**
   * Tests negative cache_max_age is rejected.
   *
   * @covers ::validateForm
   */
  public function testNegativeCacheMaxAgeRejected(): void {
    $edit = [
      'cache_max_age' => -1,
    ];

    $this->drupalGet('/admin/config/sitewide_alerts');
    $this->submitForm($edit, 'Save configuration');

    $this->assertSession()->pageTextContains('can not be negative');
  }

  /**
   * Tests alert styles are parsed correctly by AlertStyleProvider.
   *
   * @covers ::submitForm
   */
  public function testAlertStylesParsing(): void {
    $edit = [
      'alert_styles' => "info|Information\ndanger|Very Important\nsuccess|Success",
    ];

    $this->drupalGet('/admin/config/sitewide_alerts');
    $this->submitForm($edit, 'Save configuration');

    $styles = AlertStyleProvider::alertStyles();
    $this->assertArrayHasKey('info', $styles);
    $this->assertEquals('Information', $styles['info']);
    $this->assertArrayHasKey('danger', $styles);
    $this->assertEquals('Very Important', $styles['danger']);
    $this->assertArrayHasKey('success', $styles);
    $this->assertEquals('Success', $styles['success']);
  }

}
