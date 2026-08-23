<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Tests\ui_patterns\Kernel\PropTypeNormalizationTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\BooleanPropType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test BooleanPropType normalization.
 *
 * @internal
 */
#[CoversClass(BooleanPropType::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class BooleanPropTypeTest extends PropTypeNormalizationTestBase {

  /**
   * Test normalize static method.
   */
  public function testNormalization(): void {
    foreach (self::normalizationTests() as $name => [$value, $expected]) {
      $normalized = BooleanPropType::normalize($value, $this->testComponentProps['boolean']);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test rendered component with prop.
   */
  public function testRendering(): void {
    foreach (self::renderingTests() as $name => [$value, $rendered_value]) {
      $this->runRenderPropTest('boolean', ['value' => $value, 'rendered_value' => $rendered_value], (string) $name);
    }
  }

  /**
   * Test rendered component with prop default false.
   */
  public function testRenderingDefaultFalse(): void {
    foreach (self::renderingTestsDefaultFalse() as $name => [$value, $rendered_value]) {
      $this->runRenderPropTest('boolean_with_default_false', ['value' => $value, 'rendered_value' => $rendered_value], (string) $name);
    }
  }

  /**
   * Test rendered component with prop default true.
   */
  public function testRenderingDefaultTrue(): void {
    foreach (self::renderingTestsDefaultTrue() as $name => [$value, $rendered_value]) {
      $this->runRenderPropTest('boolean_with_default_true', ['value' => $value, 'rendered_value' => $rendered_value], (string) $name);
    }
  }

  /**
   * Provides data for testNormalization.
   */
  public static function normalizationTests(): array {
    return [
      'null value' => [NULL, NULL],
      'false value' => [FALSE, FALSE],
      'true value' => [TRUE, TRUE],
      'integer 0' => [0, FALSE],
      'integer pos' => [22, TRUE],
      'string empty' => ['', FALSE],
      'string zero' => ['0', FALSE],
      'string not zero' => ['22', TRUE],
      'html' => ['<p>0</p>', TRUE],
      'markup 0' => [['#markup' => '0'], FALSE],
      'markup 1' => [['#markup' => '1'], TRUE],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTests(): array {
    return [
      'null value' => [
        NULL,
        '<div class="ui-patterns-props-boolean"></div>',
      ],
      'false value' => [
        FALSE,
        '<div class="ui-patterns-props-boolean"></div>',
      ],
      'true value' => [
        TRUE,
        '<div class="ui-patterns-props-boolean">1</div>',
      ],
      'zero string value' => [
        '0',
        '<div class="ui-patterns-props-boolean"></div>',
      ],
      'not zero string value' => [
        '22',
        '<div class="ui-patterns-props-boolean">1</div>',
      ],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTestsDefaultFalse(): array {
    return [
      'null value' => [
        NULL,
        '<div class="ui-patterns-props-boolean_with_default_false"></div>',
      ],
      'false value' => [
        FALSE,
        '<div class="ui-patterns-props-boolean_with_default_false"></div>',
      ],
      'true value' => [
        TRUE,
        '<div class="ui-patterns-props-boolean_with_default_false">1</div>',
      ],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTestsDefaultTrue(): array {
    return [
      'null value' => [
        NULL,
        '<div class="ui-patterns-props-boolean_with_default_true">1</div>',
      ],
      'false value' => [
        FALSE,
        '<div class="ui-patterns-props-boolean_with_default_true"></div>',
      ],
      'true value' => [
        TRUE,
        '<div class="ui-patterns-props-boolean_with_default_true">1</div>',
      ],
    ];
  }

}
