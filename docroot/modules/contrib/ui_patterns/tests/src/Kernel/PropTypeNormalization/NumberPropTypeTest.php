<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Core\Render\Markup;
use Drupal\Tests\ui_patterns\Kernel\PropTypeNormalizationTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\NumberPropType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test NumberPropType normalization.
 *
 * @internal
 */
#[CoversClass(NumberPropType::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class NumberPropTypeTest extends PropTypeNormalizationTestBase {

  /**
   * Test normalize static method with prop number.
   */
  public function testNormalization(): void {
    foreach (self::normalizationTests() as $name => [$value, $expected]) {
      $normalized = NumberPropType::normalize($value, $this->testComponentProps['number']);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test normalize static method with prop number_with_min_max.
   */
  public function testNormalizationNumberMinMax(): void {
    foreach (self::normalizationTestsMinMax() as $name => [$value, $expected]) {
      $normalized = NumberPropType::normalize($value, $this->testComponentProps['number_with_min_max']);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test normalize static method with prop integer.
   */
  public function testNormalizationInteger(): void {
    foreach (self::normalizationTestsInteger() as $name => [$value, $expected]) {
      $normalized = NumberPropType::normalize($value, $this->testComponentProps['integer']);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test normalize static method with prop integer_with_min_max.
   */
  public function testNormalizationIntegerMinMax(): void {
    foreach (self::normalizationTestsIntegerMinMax() as $name => [$value, $expected]) {
      $normalized = NumberPropType::normalize($value, $this->testComponentProps['integer_with_min_max']);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test rendered component with prop.
   */
  public function testRendering(): void {
    foreach (self::renderingTests() as $name => [$value, $rendered_value]) {
      $this->runRenderPropTest('number', ['value' => $value, 'rendered_value' => $rendered_value], (string) $name);
    }
  }

  /**
   * Provides data for testNormalization.
   */
  public static function normalizationTests(): array {
    return [
      'null value' => [NULL, NULL],
      'integer value' => [1, 1],
      'float value' => [1.1, 1.1],
      'string value' => ['1', 1],
      'float string value' => ['1.1', 1.1],
      'markup value' => [Markup::create('3.14'), 3.14],
    ];
  }

  /**
   * Provides data for testNormalizationInteger.
   */
  public static function normalizationTestsInteger(): array {
    return [
      'null value' => [NULL, NULL],
      'integer value to integer' => [1, 1],
      'float value to integer' => [1.1, 1],
      'string value to integer' => ['1', 1],
      'float string value to integer' => ['1.1', 1],
      'markup value to integer' => [Markup::create('7'), 7],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function normalizationTestsMinMax(): array {
    return [
      'null value' => [NULL, NULL],
      'integer value' => [5, 5],
      'integer value below' => [2, NULL],
      'integer value above' => [332, NULL],
      'float value' => [10, 10],
      'float value below' => [-4.0, NULL],
      'float value above' => [345.65, NULL],
      'string value above' => ['12', NULL],
      'string value below' => ['2', NULL],
      'float string value' => ['1.1', NULL],
    ];
  }

  /**
   * Provides data for testNormalizationInteger.
   */
  public static function normalizationTestsIntegerMinMax(): array {
    return [
      'null value' => [NULL, NULL],
      'integer value to integer' => [7, 7],
      'integer value below' => [2, NULL],
      'integer value above' => [332, NULL],
      'float value to integer' => [7.1, 7],
      'string value to integer' => ['8', 8],
      'string value above' => ['12', NULL],
      'string value below' => ['2', NULL],
      'float string value to integer' => ['8.1', 8],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTests(): array {
    return [
      'null value' => [
        NULL,
        '<div class="ui-patterns-props-number"></div>',
      ],
    ];
  }

}
