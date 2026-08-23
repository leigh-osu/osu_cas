<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Tests\ui_patterns\Kernel\PropTypeNormalizationTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\IdentifierPropType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Twig\Error\RuntimeError;

/**
 * Test IdentifierPropType normalization.
 *
 * @internal
 */
#[CoversClass(IdentifierPropType::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class IdentifierPropTypeTest extends PropTypeNormalizationTestBase {

  /**
   * Test normalize static method.
   */
  public function testNormalization(): void {
    foreach (self::normalizationTests() as $name => [$value, $expected]) {
      $normalized = IdentifierPropType::normalize($value, $this->testComponentProps['identifier']);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test rendered component with prop.
   */
  public function testRendering(): void {
    foreach (self::renderingTests() as $name => $case) {
      $this->runRenderPropTest('identifier', [
        'value' => $case[0],
        'rendered_value' => $case[1],
        'exception_class' => $case[2] ?? NULL,
      ], (string) $name);
    }
  }

  /**
   * Provides data for testNormalization.
   */
  public static function normalizationTests(): array {
    return [
      'null value' => [NULL, NULL],
      'markup' => [['#markup' => 'abc'], 'abc'],
      'string' => ['abc', 'abc'],
      'string with markup' => ['<b>abc</b>', 'abc'],
      'string with square brackets' => ['a[v][eee]', 'a-v--eee-'],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTests(): array {
    return [
      'null value' => [
        NULL,
        '<div class="ui-patterns-props-identifier"></div>',
        RuntimeError::class,
      ],
      'empty value' => [
        '',
        '<div class="ui-patterns-props-identifier"></div>',
        RuntimeError::class,
      ],
      'invalid value' => [
        '2',
        '<div class="ui-patterns-props-identifier"></div>',
        RuntimeError::class,
      ],
      'correct value' => [
        'correct-value🔥',
        '<div class="ui-patterns-props-identifier">correct-value🔥</div>',
      ],
      'corrected value' => [
        'value with space and /',
        '<div class="ui-patterns-props-identifier">value-with-space-and--</div>',
      ],
    ];
  }

}
