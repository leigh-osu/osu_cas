<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Tests\ui_patterns\Kernel\PropTypeNormalizationTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\VariantPropType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test VariantPropType normalization.
 *
 * @internal
 */
#[CoversClass(VariantPropType::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class VariantPropTypeTest extends PropTypeNormalizationTestBase {

  /**
   * Test normalize static method.
   */
  public function testNormalization(): void {
    foreach (self::normalizationTests() as $name => [$value, $expected]) {
      $normalized = VariantPropType::normalize($value, $this->testComponentProps['variant']);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test rendered component with prop.
   */
  public function testRendering(): void {
    foreach (self::renderingTests() as $name => [$value, $rendered_value]) {
      $this->runRenderPropTest('variant', ['value' => $value, 'rendered_value' => $rendered_value], (string) $name);
    }
  }

  /**
   * Provides data for testNormalization.
   */
  public static function normalizationTests(): array {
    return [
      'null value' => [NULL, ''],
      'empty string' => ['', ''],
      'default value' => ['default', 'default'],
      'other value' => ['other', 'other'],
      'bad value' => ['BAD', ''],
      'integer value' => [2, ''],
      'array value' => [[], ''],
      'object value' => [new \stdClass(), ''],
      'render array' => [['#markup' => 'other'], 'other'],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTests(): array {
    return [
      'null value' => [
        NULL,
        ' class="ui-patterns-test-component ui-patterns-test-component-variant-"',
      ],
      'empty value' => [
        '',
        ' class="ui-patterns-test-component ui-patterns-test-component-variant-"',
      ],
      'other' => [
        'other',
        ' class="ui-patterns-test-component ui-patterns-test-component-variant-other"',
      ],
    ];
  }

}
