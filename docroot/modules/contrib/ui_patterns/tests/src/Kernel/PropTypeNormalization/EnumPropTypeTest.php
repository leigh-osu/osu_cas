<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Tests\ui_patterns\Kernel\PropTypeNormalizationTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\EnumPropType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test EnumPropType normalization.
 *
 * @internal
 */
#[CoversClass(EnumPropType::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class EnumPropTypeTest extends PropTypeNormalizationTestBase {

  /**
   * Test normalize static method.
   */
  public function testNormalization(): void {
    foreach (self::normalizationTests() as $name => [$value, $expected]) {
      $normalized = EnumPropType::normalize($value, $this->testComponentProps['enum_integer']);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test rendered component with prop.
   */
  public function testRendering(): void {
    foreach (self::renderingTests() as $name => [$value, $rendered_value]) {
      $this->runRenderPropTest('enum_integer', ['value' => $value, 'rendered_value' => $rendered_value], (string) $name);
    }
  }

  /**
   * Provides data for testNormalization.
   */
  public static function normalizationTests(): array {
    return [
      'null value' => [NULL, NULL],
      'integer' => [2, 2],
      'string' => ['2', 2],
      'string bad' => ['BAD VALUE', NULL],
      'object' => [new \stdClass(), NULL],
      'array' => [[2], 2],
      'array assoc' => [['aa' => 2], 2],
      'array assoc bad' => [['1' => NULL, 'aa' => 2], 2],
      'array markup' => [['#markup' => '2'], 2],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTests(): array {
    return [
      'null value' => [
        NULL,
        '<div class="ui-patterns-props-enum_integer"></div>',
      ],
    ];
  }

}
