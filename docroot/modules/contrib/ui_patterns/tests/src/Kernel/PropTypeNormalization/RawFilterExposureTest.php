<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Tests\ui_patterns\Kernel\PropTypeNormalizationTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Documents the |raw contract for untrusted props.
 *
 * Props are escaped by Twig autoescape at print time. A template that
 * prints an untrusted prop with |raw bypasses that protection on purpose:
 * this is the template author's responsibility, exactly as in any Drupal
 * template. This test pins that contract so a change to it is conscious.
 *
 * @internal
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class RawFilterExposureTest extends PropTypeNormalizationTestBase {

  /**
   * Escapes an untrusted string with {{ prop }}; {{ prop|raw }} does not.
   */
  public function testRawFilterBypassesAutoescape(): void {
    $payload = '<em>raw & html</em>';

    $build = [
      '#type' => 'component',
      '#component' => 'ui_patterns_test:test-component',
      '#props' => ['string' => $payload],
    ];
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('&lt;em&gt;raw &amp; html&lt;/em&gt;', $html);

    $build = [
      '#type' => 'component',
      '#component' => 'ui_patterns_test:test-raw-prop',
      '#props' => ['string' => $payload],
    ];
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('<em>raw & html</em>', $html);
  }

}
