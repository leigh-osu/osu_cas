<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Tests\ui_patterns\Kernel\PropTypeNormalizationTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\StringPropType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test StringPropType normalization.
 *
 * @internal
 */
#[CoversClass(StringPropType::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class StringPropTypeTest extends PropTypeNormalizationTestBase {

  /**
   * Test normalize static method.
   */
  public function testNormalization(): void {
    foreach (self::normalizationTests() as $name => [$value, $expected]) {
      $normalized = StringPropType::normalize($value, $this->testComponentProps['string']);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test rendered component with prop.
   */
  public function testRendering(): void {
    foreach (self::renderingTests() as $name => [$value, $rendered_value]) {
      $this->runRenderPropTest('string', ['value' => $value, 'rendered_value' => $rendered_value], (string) $name);
    }
  }

  /**
   * Test rendered component with prop and contentMediaType.
   */
  public function testRenderingStringPlain(): void {
    foreach (self::renderingTestsStringPlain() as $name => [$value, $rendered_value]) {
      $this->runRenderPropTest('string_plain', ['value' => $value, 'rendered_value' => $rendered_value], (string) $name);
    }
  }

  /**
   * A plain string prop containing <script> renders no live script tag.
   *
   * Mirrors a raw entity-field value (e.g. via FieldPropertySource)
   * reaching a string prop.
   */
  public function testScriptTagInStringPropIsStripped(): void {
    $this->assertExpectedOutput(
      [
        'rendered_value' => '<script',
        'assert' => 'assertStringNotContainsString',
      ],
      [
        '#type' => 'component',
        '#component' => 'ui_patterns_test:test-component',
        '#props' => ['string' => 'Hello<script>alert("xss")</script>World'],
      ]
    );
  }

  /**
   * A plain string prop containing <iframe> renders no live iframe.
   */
  public function testIframeTagInStringPropIsStripped(): void {
    $this->assertExpectedOutput(
      [
        'rendered_value' => '<iframe',
        'assert' => 'assertStringNotContainsString',
      ],
      [
        '#type' => 'component',
        '#component' => 'ui_patterns_test:test-component',
        '#props' => ['string' => '<iframe src="https://evil.example.org"></iframe>'],
      ]
    );
  }

  /**
   * A plain string prop with dangerous markup is fully escaped when rendered.
   *
   * One combined payload (script, anchor, img, svg): the rendered HTML must
   * contain the whole entity-encoded form, so a regression on any vector
   * fails with a precise diff.
   */
  public function testDangerousMarkupInPlainStringPropIsEscaped(): void {
    $payload = '<script>alert("xss")</script><a href="https://phishing.example/" onclick="alert(1)">Sign in</a><img src="x" onerror="alert(1)"><svg onload="alert(1)"><path d="M0 0"/></svg>';
    $expected = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;&lt;a href=&quot;https://phishing.example/&quot; onclick=&quot;alert(1)&quot;&gt;Sign in&lt;/a&gt;&lt;img src=&quot;x&quot; onerror=&quot;alert(1)&quot;&gt;&lt;svg onload=&quot;alert(1)&quot;&gt;&lt;path d=&quot;M0 0&quot;/&gt;&lt;/svg&gt;';
    $this->runRenderPropTest('string', [
      'value' => $payload,
      'rendered_value' => $expected,
    ], 'dangerous markup escaped in rendered output');
    $this->runRenderPropTest('string', [
      'value' => $payload,
      'rendered_value' => '<script',
      'assert' => 'assertStringNotContainsString',
    ], 'no live script tag in rendered output');
  }

  /**
   * A Markup object in a string prop is trusted and passed through.
   *
   * Markup::create() is how a caller opts into raw HTML; ui_patterns does
   * not re-escape it.
   */
  public function testMarkupObjectInStringPropIsTrusted(): void {
    $this->assertExpectedOutput(
      [
        'rendered_value' => '<script>alert("trusted")</script>',
        'assert' => 'assertStringContainsString',
      ],
      [
        '#type' => 'component',
        '#component' => 'ui_patterns_test:test-component',
        '#props' => ['string' => Markup::create('<script>alert("trusted")</script>')],
      ]
    );
  }

  /**
   * Provides data for testNormalization.
   */
  public static function normalizationTests(): array {
    return [
      'null value' => [NULL, ''],
      'string' => ['my string', 'my string'],
      'string empty' => ['', ''],
      'int' => [2, '2'],
      'render array' => [['#markup' => 'my string'], 'my string'],
      'string with markup' => [Markup::create('my string'), 'my string'],
      'string with url' => [Url::fromUri('https://drupal.org'), 'https://drupal.org'],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTests(): array {
    return [
      'null value' => [
        NULL,
        '<div class="ui-patterns-props-string"></div>',
      ],
      'string with link' => [
        Link::fromTextAndUrl(Markup::create('test'), Url::fromUri('https://drupal.org')),
        '<div class="ui-patterns-props-string"><a href="https://drupal.org">test</a></div>',
      ],
      'html string' => [
        // A plain string is untrusted and fully escaped; the next case
        // uses Markup::create() to opt into raw HTML.
        '<form><input type="checkbox" /></form><b>test</b>',
        '<div class="ui-patterns-props-string">&lt;form&gt;&lt;input type=&quot;checkbox&quot; /&gt;&lt;/form&gt;&lt;b&gt;test&lt;/b&gt;</div>',
      ],
      'html markup object' => [
        Markup::create('<form><input type="checkbox" /></form><b>test</b>'),
        '<div class="ui-patterns-props-string"><form><input type="checkbox" /></form><b>test</b></div>',
      ],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTestsStringPlain(): array {
    return [
      'null value' => [
        NULL,
        '<div class="ui-patterns-props-string_plain"></div>',
      ],
      'string with link' => [
        Link::fromTextAndUrl(Markup::create('test'), Url::fromUri('https://drupal.org')),
        '<div class="ui-patterns-props-string_plain">test</div>',
      ],
      'html string' => [
        '<b>test</b>',
        '<div class="ui-patterns-props-string_plain">test</div>',
      ],
      'html markup object' => [
        Markup::create('<b>test</b>'),
        '<div class="ui-patterns-props-string_plain">test</div>',
      ],
    ];
  }

}
