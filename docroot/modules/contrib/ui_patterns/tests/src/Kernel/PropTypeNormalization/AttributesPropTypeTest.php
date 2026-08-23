<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Core\Template\Attribute;
use Drupal\Tests\ui_patterns\Kernel\PropTypeNormalizationTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\AttributesPropType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test AttributesPropType normalization.
 *
 * @internal
 */
#[CoversClass(AttributesPropType::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class AttributesPropTypeTest extends PropTypeNormalizationTestBase {

  /**
   * Test normalize static method.
   */
  public function testNormalization(): void {
    foreach (self::normalizationTests() as $name => [$value, $expected]) {
      $normalized = AttributesPropType::normalize($value, $this->testComponentProps['attributes']);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test rendered component with prop.
   */
  public function testRendering(): void {
    foreach (self::renderingTests() as $name => [$value, $rendered_value]) {
      $this->runRenderPropTest('attributes', ['value' => $value, 'rendered_value' => $rendered_value], (string) $name);
    }
  }

  /**
   * Literal angle brackets in an attribute value are preserved on the wire.
   *
   * Regression guard: normalize() should not damage legitimate values
   * like data-pattern with '<' or '>' literals.
   * HTML-context escaping is applied at render time by Attribute::__toString().
   *
   * @see \Drupal\ui_patterns\Plugin\UiPatterns\PropType\AttributesPropType::normalizeAttrValue()
   */
  public function testLiteralAngleBracketsInAttributeValueArePreserved(): void {
    $normalized = AttributesPropType::normalize(
      ['data-pattern' => '^<.*>$'],
      $this->testComponentProps['attributes']
    );
    self::assertSame('^<.*>$', $normalized['data-pattern']);
    // And the rendered output entity-encodes for HTML attribute context.
    $this->runRenderPropTest('attributes', [
      'value' => ['data-pattern' => '^<.*>$'],
      'rendered_value' => ' data-pattern="^&lt;.*&gt;$"',
    ]);
  }

  /**
   * Attribute value escaping matches Drupal's native `create_attribute`.
   *
   * @see \Drupal\ui_patterns\Plugin\UiPatterns\PropType\AttributesPropType::normalizeAttrValue()
   */
  public function testAttributeValueEscapingMatchesDrupalCore(): void {
    $cases = [
      'tagged_text' => [
        'value' => 'Hello <em>world</em> and <<not-a-tag>> and <123> and <em2>OK</em2>.',
        'expected_attr' => 'data-foo="Hello &lt;em&gt;world&lt;/em&gt; and &lt;&lt;not-a-tag&gt;&gt; and &lt;123&gt; and &lt;em2&gt;OK&lt;/em2&gt;."',
      ],
      'email_in_angle_brackets' => [
        'value' => 'Email: <john@example.com>',
        'expected_attr' => 'data-foo="Email: &lt;john@example.com&gt;"',
      ],
      'regex_anchors' => [
        'value' => '^<.*>$',
        'expected_attr' => 'data-foo="^&lt;.*&gt;$"',
      ],
    ];

    foreach ($cases as $name => $case) {
      // Case 1 — Drupal native: `create_attribute()` directly in Twig.
      // This is the reference behavior we want to match.
      $this->assertExpectedOutput(
        [
          'rendered_value' => $case['expected_attr'],
          'assert' => 'assertStringContainsString',
        ],
        [
          '#type' => 'inline_template',
          '#template' => "{% set btn = create_attribute({'data-foo': v}) %}<button{{ btn }}>test</button>",
          '#context' => ['v' => $case['value']],
        ]
      );

      // Case 2 — ui_patterns: same value via the `attributes` prop of an
      // SDC component. Must produce identical attribute serialization.
      $this->assertExpectedOutput(
        [
          'rendered_value' => $case['expected_attr'],
          'assert' => 'assertStringContainsString',
        ],
        [
          '#type' => 'component',
          '#component' => 'ui_patterns_test:test-component',
          '#props' => ['attributes' => ['data-foo' => $case['value']]],
        ],
        \sprintf('Case "%s" failed: ui_patterns attributes prop does not match Drupal native create_attribute output.', $name)
      );
    }
  }

  /**
   * Provides data for testNormalization.
   */
  public static function normalizationTests(): array {
    return [
      'Empty value' => [[], []],
      'Standardized primitives, so already OK' => self::standardizedPrimitives(),
      'Type transformations' => self::typeTransformation(),
      'List array' => self::listArray(),
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTests(): array {
    return [
      'Empty Value' => [
        [],
        '<div data-component-id="ui_patterns_test:test-component"></div>',
      ],
      'attribute_object' => [
        new Attribute(['data-foo' => 'bar']),
        ' data-foo="bar"',
      ],
      'integer' => [
        ['data-foo' => 1],
        ' data-foo="1"',
      ],
      'array' => [
        ['key' => ['1', '2']],
        ' key="1 2"',
      ],
      'escaping' => [
        ['key' => '"'],
        ' key="&quot;"',
      ],
      'rendered value' => [
        ['key' => ['#markup' => 'value']],
        ' key="value"',
      ],
    ];
  }

  /**
   * Standardized primitives, so already OK.
   */
  protected static function standardizedPrimitives() {
    $value = [
      'foo' => 'bar',
      'string' => 'Lorem ipsum',
      'array' => [
        'One',
        'Two',
        3,
      ],
      'integer' => 4,
      'float' => 1.4,
    ];

    return [$value, $value];
  }

  /**
   * Type transformations.
   */
  protected static function typeTransformation() {
    $value = [
      'true_boolean' => TRUE,
      'false_boolean' => FALSE,
      'null_boolean' => NULL,
      'markup' => 'Hello <b>World</b>',
      'associative_array' => [
        'Un' => 'One',
        'Deux' => 'Two',
        'Trois' => 3,
      ],
      'nested_array' => [
        'One',
        [
          'Two',
          'Three',
        ],
        [
          'deep' => [
            'very deep' => ['foo', 'bar'],
          ],
        ],
      ],
    ];
    $expected = [
      'true_boolean' => '1',
      'false_boolean' => '',
      'null_boolean' => '',
      // Angle brackets in attribute values are no longer stripped by
      // normalize(). Attribute::__toString() handles HTML-context
      // escaping at render time (see rendering tests below for the
      // escaped on-the-wire form).
      'markup' => 'Hello <b>World</b>',
      'associative_array' => [
        'One',
        'Two',
        3,
      ],
      'nested_array' => [
        'One',
        // JSON encoding because we don't know how deep is the nesting.
        '["Two","Three"]',
        '{"deep":{"very deep":["foo","bar"]}}',
      ],
    ];

    return [$value, $expected];
  }

  /**
   * List array.
   */
  protected static function listArray() {
    $value = [
      'One',
      'Two',
      3,
    ];
    // This doesn't look like a valid HTML attribute structure, but we rely on
    // Drupal Attribute object normalization here.
    $expected = [
      '0' => 'One',
      '1' => 'Two',
      '2' => 3,
    ];

    return [$value, $expected];
  }

}
