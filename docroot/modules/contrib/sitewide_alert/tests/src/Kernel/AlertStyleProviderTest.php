<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sitewide_alert\AlertStyleProvider;

/**
 * Tests the AlertStyleProvider static methods.
 *
 * @group sitewide_alert
 * @coversDefaultClass \Drupal\sitewide_alert\AlertStyleProvider
 */
final class AlertStyleProviderTest extends SitewideAlertKernelTestBase {

  /**
   * Tests parsing key|value format.
   *
   * @covers ::alertStyles
   */
  public function testParsesKeyValueFormat(): void {
    \Drupal::configFactory()->getEditable('sitewide_alert.settings')
      ->set('alert_styles', "info|Information")
      ->save();

    $styles = AlertStyleProvider::alertStyles();
    $this->assertArrayHasKey('info', $styles);
    $this->assertEquals('Information', $styles['info']);
  }

  /**
   * Tests entry without pipe uses Html::cleanCssIdentifier as key.
   *
   * @covers ::alertStyles
   */
  public function testHandlesEntryWithoutPipe(): void {
    \Drupal::configFactory()->getEditable('sitewide_alert.settings')
      ->set('alert_styles', 'My Custom Style')
      ->save();

    $styles = AlertStyleProvider::alertStyles();
    // Html::cleanCssIdentifier converts spaces to hyphens.
    $this->assertArrayHasKey('My-Custom-Style', $styles);
    $this->assertEquals('My Custom Style', $styles['My-Custom-Style']);
  }

  /**
   * Tests empty config returns empty array.
   *
   * @covers ::alertStyles
   */
  public function testEmptyConfigReturnsEmptyArray(): void {
    \Drupal::configFactory()->getEditable('sitewide_alert.settings')
      ->set('alert_styles', '')
      ->save();

    $styles = AlertStyleProvider::alertStyles();
    $this->assertIsArray($styles);
    $this->assertEmpty($styles);
  }

  /**
   * Tests alertStyleName returns label for known key.
   *
   * @covers ::alertStyleName
   */
  public function testAlertStyleNameReturnsLabel(): void {
    \Drupal::configFactory()->getEditable('sitewide_alert.settings')
      ->set('alert_styles', "primary|Primary\ninfo|Information")
      ->save();

    $label = AlertStyleProvider::alertStyleName('primary');
    $this->assertEquals('Primary', $label);
  }

  /**
   * Tests alertStyleName returns N/A for unknown key.
   *
   * @covers ::alertStyleName
   */
  public function testAlertStyleNameReturnsNaForUnknown(): void {
    \Drupal::configFactory()->getEditable('sitewide_alert.settings')
      ->set('alert_styles', "primary|Primary")
      ->save();

    $label = AlertStyleProvider::alertStyleName('nonexistent');
    $this->assertInstanceOf(TranslatableMarkup::class, $label);
    $this->assertEquals('N/A', (string) $label);
  }

}
