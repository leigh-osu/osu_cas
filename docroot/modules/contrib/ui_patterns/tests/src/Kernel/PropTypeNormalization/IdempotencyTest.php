<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Core\Render\Markup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_patterns\PropTypeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests normalize() idempotency: normalize(normalize(x)) === normalize(x).
 *
 * A value can be normalized more than once: a source may pre-normalize,
 * then the render pipeline normalizes again, and nested components
 * re-enter the pipeline. A non-idempotent normalize() corrupts the value
 * on the second pass (e.g. double-escaped menu titles, #3610847).
 *
 * @internal
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class IdempotencyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_patterns',
    'ui_patterns_test',
  ];

  /**
   * Asserts idempotency for every prop type with a representative case.
   */
  public function testNormalizeIsIdempotent(): void {
    $manager = \Drupal::service('plugin.manager.ui_patterns_prop_type');
    $cases = $this->cases();
    $checked = 0;

    foreach (array_keys($manager->getDefinitions()) as $id) {
      if (!isset($cases[$id])) {
        continue;
      }
      [$values, $definition] = $cases[$id];
      $instance = $manager->createInstance($id, []);
      $this->assertInstanceOf(PropTypeInterface::class, $instance);
      foreach ($values as $value) {
        $once = $instance::normalize($value, $definition);
        $twice = $instance::normalize($once, $definition);
        $this->assertSame(
          $this->canonical($once),
          $this->canonical($twice),
          sprintf('normalize() is not idempotent for prop type "%s" with value %s', $id, var_export($value, TRUE))
        );
        $checked++;
      }
    }
    $this->assertGreaterThan(0, $checked);
  }

  /**
   * Prop type plugin id => [representative values, definition].
   */
  private function cases(): array {
    $component = \Drupal::service('plugin.manager.sdc')->find('ui_patterns_test:test-component');
    $props = $component->metadata->schema['properties'] ?? [];
    return [
      'string' => [['A & B', '<script>alert(1)</script>', 'A &amp; B'], $props['string'] ?? NULL],
      'links' => [
        [
          [['title' => 'Tom & Jerry', 'url' => '/x']],
          [['title' => '<b>x & y</b>', 'url' => '/x', 'below' => [['title' => 'C & D', 'url' => '/y']]]],
        ],
        $props['links'] ?? NULL,
      ],
      'slot' => [['A & B', '<script>alert(1)</script>', Markup::create('<b>safe</b>')], NULL],
      'attributes' => [[['title' => 'A & B', 'class' => ['x']]], $props['attributes_ui_patterns'] ?? NULL],
      'identifier' => [['A & B id'], $props['identifier'] ?? NULL],
      'url' => [['https://example.com/?a=1&b=2'], $props['url'] ?? NULL],
      'number' => [['3.14', 7], $props['number'] ?? NULL],
      'boolean' => [[TRUE, '1'], $props['boolean'] ?? NULL],
      'enum' => [['2'], $props['enum_string'] ?? NULL],
      'enum_list' => [[['A', 'B']], $props['enum_list'] ?? NULL],
      'enum_set' => [[[1, 2]], $props['enum_set'] ?? NULL],
      'list' => [[['A & B', 'c']], $props['list_string'] ?? NULL],
    ];
  }

  /**
   * Stringifies objects so equal values in different instances compare.
   */
  private function canonical(mixed $value): mixed {
    if ($value instanceof \Stringable) {
      return get_class($value) . ':' . (string) $value;
    }
    if (is_array($value)) {
      return array_map(fn ($item) => $this->canonical($item), $value);
    }
    return $value;
  }

}
