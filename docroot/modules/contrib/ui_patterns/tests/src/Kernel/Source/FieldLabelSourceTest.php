<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\FieldLabelSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test FieldLabelSource.
 *
 * @internal
 */
#[CoversClass(FieldLabelSource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class FieldLabelSourceTest extends SourcePluginsTestBase {

  /**
   * Test Field Property Plugin.
   */
  public function testPlugin(): void {
    $testData = self::loadTestDataFixture(__DIR__ . '/../../../fixtures/tests.formatter_per_item.yml');
    $testSets = $testData->getTestSets();

    foreach ($testSets as $test_set_name => $test_set) {
      if (!\str_starts_with($test_set_name, 'field_label_')) {
        continue;
      }
      $this->runSourcePluginTest($test_set);
    }
  }

  /**
   * A label with an ampersand renders escaped exactly once (#3610847).
   */
  public function testAmpersandLabelIsNotDoubleEscaped(): void {
    $field = FieldConfig::loadByName('node', 'page', 'field_text_1');
    $this->assertNotNull($field);
    $field->set('label', 'Tom & Jerry')->save();

    $captured = NULL;
    $this->runSourcePluginTest([
      'skip_schema_check' => TRUE,
      'component' => [
        'component_id' => 'ui_patterns_test:test-component',
        'props' => [
          'string' => ['source_id' => 'field_label', 'source' => []],
        ],
      ],
      'entity' => ['field_text_1' => ['value' => 'x']],
      'contexts' => ['field_name' => 'field_text_1', 'bundle' => 'page'],
      'output' => [
        'props' => [
          'string' => [
            'closure' => function ($output) use (&$captured) {
              $this->assertSame('Tom & Jerry', (string) $output);
              $captured = $output;
            },
          ],
        ],
      ],
    ]);
    $build = [
      '#type' => 'component',
      '#component' => 'ui_patterns_test:test-component',
      '#props' => ['string' => $captured],
    ];
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Tom &amp; Jerry', $html);
    $this->assertStringNotContainsString('Tom &amp;amp; Jerry', $html, 'Label is double-escaped.');
  }

}
