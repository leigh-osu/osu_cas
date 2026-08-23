<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\ui_patterns\Kernel\PropTypeNormalizationTestBase;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\SlotPropType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test SlotPropType normalization.
 *
 * @internal
 */
#[CoversClass(SlotPropType::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class SlotPropTypeTest extends PropTypeNormalizationTestBase {

  /**
   * Test normalize static method.
   */
  public function testNormalization(): void {
    foreach (self::normalizationTests() as $name => [$value, $expected]) {
      $normalized = SlotPropType::normalize($value);
      self::assertEquals($normalized, $expected, (string) $name);
    }
  }

  /**
   * Test rendered component with prop.
   */
  public function testRendering(): void {
    foreach (self::renderingTests() as $name => [$value, $rendered_value]) {
      $this->runRenderPropTest('slot', ['value' => $value, 'rendered_value' => $rendered_value], (string) $name);
    }
  }

  /**
   * A plain string slot value is escaped, never rendered as markup.
   *
   * SlotPropType::normalize() routes plain strings through #plain_text, so
   * Drupal core escapes them. Every tag below is escaped — this layer
   * escapes, it does not allow-list. To render raw HTML, pass a trusted
   * type instead (see testMarkupObjectInSlotIsTrusted).
   */
  public function testBareStringInSlotIsEscaped(): void {
    // Form + input become entity-escaped text.
    $this->runRenderPropTest('slot', [
      'value' => '<form><input name="x" /></form>',
      'rendered_value' => '<form',
      'assert' => 'assertStringNotContainsString',
    ]);
    $this->runRenderPropTest('slot', [
      'value' => '<form><input name="x" /></form>',
      'rendered_value' => '&lt;form',
      'assert' => 'assertStringContainsString',
    ]);
    $this->runRenderPropTest('slot', [
      'value' => '<form><input name="x" /></form>',
      'rendered_value' => '&lt;input',
      'assert' => 'assertStringContainsString',
    ]);
    // Inline SVG icon — escaped, not raw.
    $this->runRenderPropTest('slot', [
      'value' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"></path></svg>',
      'rendered_value' => '<svg',
      'assert' => 'assertStringNotContainsString',
    ]);
    $this->runRenderPropTest('slot', [
      'value' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"></path></svg>',
      'rendered_value' => '&lt;svg',
      'assert' => 'assertStringContainsString',
    ]);
    // <script> / <iframe> — escaped.
    $this->runRenderPropTest('slot', [
      'value' => '<script>alert("xss")</script>',
      'rendered_value' => '<script',
      'assert' => 'assertStringNotContainsString',
    ]);
    $this->runRenderPropTest('slot', [
      'value' => '<script>alert("xss")</script>',
      'rendered_value' => '&lt;script',
      'assert' => 'assertStringContainsString',
    ]);
    $this->runRenderPropTest('slot', [
      'value' => '<iframe src="https://example.org"></iframe>',
      'rendered_value' => '<iframe',
      'assert' => 'assertStringNotContainsString',
    ]);
    $this->runRenderPropTest('slot', [
      'value' => '<iframe src="https://example.org"></iframe>',
      'rendered_value' => '&lt;iframe',
      'assert' => 'assertStringContainsString',
    ]);
  }

  /**
   * A Markup object in a slot is trusted and passed through unescaped.
   *
   * Markup::create() is how a caller opts into raw HTML: convertObject()
   * passes it straight to #children.
   */
  public function testMarkupObjectInSlotIsTrusted(): void {
    $normalized = SlotPropType::normalize(Markup::create('<script>alert("trusted")</script>'));
    $rendered = (string) ($normalized['#children'] ?? '');
    self::assertStringContainsString('<script', $rendered);
  }

  /**
   * A #markup render-array slot is filtered by Drupal core.
   *
   * Core runs Xss::filterAdmin over #markup; ui_patterns does not filter
   * render arrays itself.
   */
  public function testScriptInMarkupRenderArraySlotIsStrippedByCore(): void {
    $this->runRenderPropTest('slot', [
      'value' => ['#markup' => '<script>alert("core")</script>Hello'],
      'rendered_value' => '<script',
      'assert' => 'assertStringNotContainsString',
    ]);
  }

  /**
   * A `#plain_text` render-array slot is HTML-escaped by Drupal core.
   */
  public function testScriptInPlainTextRenderArraySlotIsEscaped(): void {
    $this->runRenderPropTest('slot', [
      'value' => ['#plain_text' => '<script>alert("plain")</script>'],
      'rendered_value' => '<script',
      'assert' => 'assertStringNotContainsString',
    ]);
  }

  /**
   * Provides data for testNormalization.
   */
  public static function normalizationTests(): array {
    return [
      'null value' => [NULL, ['#cache' => []]],
      'bare string' => ['hello', ['#plain_text' => 'hello']],
      'bare html string' => ['<form>x</form>', ['#plain_text' => '<form>x</form>']],
    ];
  }

  /**
   * Provides data for testNormalization.
   */
  public static function renderingTests(): array {
    return [
      'null value' => [
        NULL,
        '<div class="ui-patterns-slots-slot"></div>',
      ],
      'string value' => ['my slot', 'my slot'],
      'string in array' => [['my slot'], 'my slot'],
      'string as array value' => [['aa' => 'my slot'], 'my slot'],
      'markup value' => [Markup::create('my slot'), 'my slot'],
      'markup in array' => [[Markup::create('my slot')], 'my slot'],
      'markup in array value' => [['uu' => Markup::create('my slot')], 'my slot'],
      'translatable' => [new TranslatableMarkup('my slot'), 'my slot'],
      't function' => [t('my slot'), 'my slot'],
      'array value' => [['#markup' => 'my slot'], 'my slot'],
      'inline template' => [['#type' => 'inline_template', '#template' => 'my slot'], 'my slot'],
      'array value with weight' => [
        ['b' => ['#weight' => 2, '#markup' => 'slot'], 'a' => ['#weight' => 1, '#markup' => 'my ']],
        'my slot',
      ],
      'render array special' => [
        [0 => ['#markup' => 'my slot', 'randomKey' => []]],
        'my slot',
      ],
    ];
  }

}
