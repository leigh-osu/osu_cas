<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\Source;

use Drupal\Tests\ui_patterns\Kernel\SourcePluginsTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\FieldPropertySource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test FieldPropertySource.
 *
 * @internal
 */
#[CoversClass(FieldPropertySource::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class FieldPropertySourceTest extends SourcePluginsTestBase {

  /**
   * Test FieldPropertySource Plugin.
   */
  public function testPlugin(): void {
    $this->runSourcePluginTests('field_property_');
  }

  /**
   * A <script> stored in an entity field reaches a slot escaped.
   *
   * Only the slot case is tested here: the string → slot conversion goes
   * through SlotPropType::convertFrom() → #plain_text, which Drupal core
   * escapes. The string-prop case belongs to the prop-type layer and is
   * covered by StringPropTypeTest::testScriptTagInStringPropIsStripped.
   */
  public function testXssInFieldValueForSlotIsStripped(): void {
    $this->runSourcePluginTest([
      'skip_schema_check' => TRUE,
      'component' => [
        'component_id' => 'ui_patterns_test:test-component',
        'slots' => [
          'slot' => [
            'sources' => [
              [
                'source_id' => 'field_property:node:field_text_1:value',
                'source' => [
                  'type' => 'string',
                ],
              ],
            ],
          ],
        ],
      ],
      'entity' => [
        'field_text_1' => [
          'value' => 'Hello<script>alert("xss")</script>World',
        ],
      ],
      'contexts' => [
        'field_name' => 'field_text_1',
        'bundle' => 'page',
      ],
      'output' => [
        'slots' => [
          'slot' => [
            [
              'rendered_value' => '<script',
              'assert' => 'assertStringNotContainsString',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * A <script> stored in an entity field reaches a string prop escaped.
   *
   * The source returns the raw string, untrusted: the rendered component
   * shows it as literal text, never as a live element.
   */
  public function testXssInFieldValueForStringPropIsEscaped(): void {
    $captured = NULL;
    $this->runSourcePluginTest([
      'skip_schema_check' => TRUE,
      'component' => [
        'component_id' => 'ui_patterns_test:test-component',
        'props' => [
          'string' => [
            'source_id' => 'field_property:node:field_text_1:value',
            'source' => [
              'type' => 'string',
            ],
          ],
        ],
      ],
      'entity' => [
        'field_text_1' => [
          'value' => 'Hello<script>alert("xss")</script>World',
        ],
      ],
      'contexts' => [
        'field_name' => 'field_text_1',
        'bundle' => 'page',
      ],
      'output' => [
        'props' => [
          'string' => [
            'closure' => function ($output) use (&$captured) {
              $this->assertSame('Hello<script>alert("xss")</script>World', $output);
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
    $this->assertStringNotContainsString('<script>alert', $html);
    $this->assertStringContainsString('Hello&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;World', $html);
  }

  /**
   * A `processed` property is trusted and renders its filtered HTML.
   *
   * Trust is carried by type: TextProcessed::getValue() returns
   * FilteredMarkup, which the string prop type keeps as safe HTML.
   */
  public function testProcessedFieldValueForStringPropIsTrusted(): void {
    $captured = NULL;
    $this->runSourcePluginTest([
      'skip_schema_check' => TRUE,
      'component' => [
        'component_id' => 'ui_patterns_test:test-component',
        'props' => [
          'string' => [
            'source_id' => 'field_property:node:field_text_1:processed',
            'source' => [
              'type' => 'string',
            ],
          ],
        ],
      ],
      'entity' => [
        'field_text_1' => [
          'value' => 'Tom & Jerry',
          'format' => 'plain_text',
        ],
      ],
      'contexts' => [
        'field_name' => 'field_text_1',
        'bundle' => 'page',
      ],
      'output' => [
        'props' => [
          'string' => [
            'closure' => function ($output) use (&$captured) {
              $this->assertInstanceOf('\Drupal\Component\Render\MarkupInterface', $output);
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
    // Single escape from the plain_text format; a double-escape would
    // show &amp;amp;.
    $this->assertStringContainsString('Tom &amp; Jerry', $html);
    $this->assertStringNotContainsString('Tom &amp;amp; Jerry', $html);
  }

}
