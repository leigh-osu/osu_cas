<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Unit;

use Drupal\Core\Render\Markup;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_patterns\Element\ComponentElementAlter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the Component alter.
 *
 * @internal
 */
#[CoversClass(ComponentElementAlter::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ComponentElementAlterTest extends UnitTestCase {

  /**
   * Test the method ::canonicalize().
   */
  #[DataProvider('provideSlots')]
  public function testIsSlotEmpty(array $slot, bool $isEmpty): void {
    self::assertEquals($isEmpty, ComponentElementAlter::isSlotEmpty($slot));
  }

  /**
   * Provide data for testCanonicalize.
   */
  public static function provideSlots(): array {
    return [
      [
        ['#markup' => ''], TRUE,
      ],
      [
        ['#markup' => Markup::create('')], TRUE,
      ],
      [
        ['#markup' => Markup::create('test')], FALSE,
      ],
      [
        ['#cache' => ['tags' => ['tag1']]], TRUE,
      ],
      [
        ['#cache' => ['tags' => ['tag2']], '#markup' => NULL], TRUE,
      ],
      [
        ['#cache' => ['tags' => ['tag2']], 'children' => [['#markup' => '']]], TRUE,
      ],
      [
        ['#plain_text' => ''], TRUE,
      ],
      [
        ['#plain_text' => '', '#preprocess' => 'dummy'], FALSE,
      ],
      [
        [
          'children' => [
            ['#markup' => ''],
            ['#markup' => ''],
          ],
        ], TRUE,
      ],
      [
        [
          'children' => [
            ['#markup' => ''],
            ['#markup' => 'TEST'],
          ],
        ], FALSE,
      ],
      [
        ['children' => ['#markup' => 'some']], FALSE,
      ],
      [
        ['#markup' => 'some data'], FALSE,
      ],
      [
        ['#theme' => 'my_theme'], FALSE,
      ],
      [
        ['#weight' => 0], TRUE,
      ],
      [
        ['#theme' => 'my_theme', '#access' => FALSE], TRUE,
      ],
    ];
  }

}
