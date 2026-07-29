<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Core\Template\Attribute;
use Drupal\Tests\ui_patterns\Kernel\PropTypeNormalizationTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\EnumSetPropType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test EnumSetPropType normalization.
 *
 * @internal
 */
#[CoversClass(EnumSetPropType::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class EnumSetPropTypeTest extends PropTypeNormalizationTestBase {

  /**
   * Test normalize static method.
   */
  public function testNormalization(): void {
    foreach (self::normalizationTests() as $name => [$value, $expected]) {
      $normalized = EnumSetPropType::normalize($value, $this->testComponentProps['enum_set']);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test rendered component with prop.
   */
  public function testRendering(): void {
    foreach (self::renderingTests() as $name => [$value, $rendered_value]) {
      $this->runRenderPropTest('enum_set', ['value' => $value, 'rendered_value' => $rendered_value], (string) $name);
    }
  }

  /**
   * Provides data for testNormalization.
   */
  public static function normalizationTests(): array {
    return [
      'null value' => [NULL, []],
      'single item' => [[2], [2]],
      'single item string' => [['2'], [2]],
      'single string' => ['2', [2]],
      'multiple items' => [[2, 2, 2], [2]],
      'multiple items with bad values' => [
        [2, 'BAD', 2, 2, 444, 'BAD', new Attribute(), [2]],
        [2],
      ],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTests(): array {
    return [
      'null value' => [
        NULL,
        '<div class="ui-patterns-props-enum_set"></div>',
      ],
      'multiple items with bad values' => [
        [2, 'BAD', '2', 2, 444, 'BAD', new Attribute(), [2]],
        '<div class="ui-patterns-props-enum_set"><span>2</span></div>',
      ],
    ];
  }

}
