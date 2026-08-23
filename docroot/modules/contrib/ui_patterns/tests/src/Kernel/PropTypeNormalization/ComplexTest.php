<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel\PropTypeNormalization;

use Drupal\Tests\ui_patterns\Kernel\PropTypeNormalizationTestBase;
use Drupal\ui_patterns\Template\TwigExtension;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test some complex cases.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ComplexTest extends PropTypeNormalizationTestBase {

  /**
   * Nested form component via bare Twig include(): output is raw markup.
   *
   * `{% set comp_form = include('...', {}) %}` is the assignment form.
   * TwigExtension::include() overrides Twig core so include() returns a
   * `Twig\Markup` object instead of a plain string (mirroring
   * twigphp/Twig#4802). That object hits SlotPropType::normalize()'s
   * is_object branch, goes through convertObject(), and renders unfiltered
   * — identical to the capture form below. The rendered output of a
   * developer-authored, autoescaped template is trusted markup.
   *
   * @see testNestedComponentWithFormViaCapturedInclude
   */
  public function testNestedComponentWithFormViaBareInclude(): void {
    $render_array = [
      '#type' => 'inline_template',
      '#template' => "
      {% set comp_form = include('ui_patterns_test:test-form-component', {}) %}
      {{ include('ui_patterns_test:test-component', {slot: comp_form}) }}",
      '#context' => [],
    ];
    $this->assertExpectedOutput(
      [
        'rendered_value' => '<input ',
        'assert' => 'assertStringContainsString',
      ],
      $render_array
    );
    $this->assertExpectedOutput(
      [
        'rendered_value' => '<form ',
        'assert' => 'assertStringContainsString',
      ],
      $render_array
    );
  }

  /**
   * Nested form component via Twig capture: output is raw markup.
   *
   * `{% set comp_form %}{{ include(...) }}{% endset %}` is the capture
   * form; Twig compiles captured blocks to a `Twig\Markup` object
   * (see vendor/twig/twig/src/Node/SetNode.php). That object hits
   * SlotPropType::normalize()'s is_object branch, goes through
   * convertObject(), and renders unfiltered.
   */
  public function testNestedComponentWithFormViaCapturedInclude(): void {
    $render_array = [
      '#type' => 'inline_template',
      '#template' => "
      {% set comp_form %}{{ include('ui_patterns_test:test-form-component', {}) }}{% endset %}
      {{ include('ui_patterns_test:test-component', {slot: comp_form}) }}",
      '#context' => [],
    ];
    $this->assertExpectedOutput(
      [
        'rendered_value' => '<input ',
        'assert' => 'assertStringContainsString',
      ],
      $render_array
    );
    $this->assertExpectedOutput(
      [
        'rendered_value' => '<form ',
        'assert' => 'assertStringContainsString',
      ],
      $render_array
    );
  }

  /**
   * Nested form component reaches the slot via a component render array.
   */
  public function testNestedComponentWithFormComponentArray(): void {
    $render_array = [
      '#type' => 'component',
      '#component' => 'ui_patterns_test:test-component',
      '#slots' => [
        'slot' => [
          '#type' => 'component',
          '#component' => 'ui_patterns_test:test-form-component',
        ],
      ],
    ];
    $this->assertExpectedOutput(
      [
        'rendered_value' => '<input ',
        'assert' => 'assertStringContainsString',
      ],
      $render_array
    );
    $this->assertExpectedOutput(
      [
        'rendered_value' => '<form ',
        'assert' => 'assertStringContainsString',
      ],
      $render_array
    );
  }

  /**
   * The include() Twig function is overridden by TwigExtension::include().
   *
   * Confirms the ui_patterns override wins over Twig core's CoreExtension
   * registration — the assumption (last-registered function wins, module
   * extensions load after core) the trust behavior of the assignment-form
   * include relies on.
   */
  public function testIncludeFunctionIsOverridden(): void {
    $function = \Drupal::service('twig')->getFunction('include');
    self::assertNotFalse($function);
    self::assertSame([TwigExtension::class, 'include'], $function->getCallable());
  }

  /**
   * Test slot normalization with Twig Markup.
   */
  public function testTwigMarkup(): void {
    // Test nested component with form.
    $render_array_tests = [
      [
        '#type' => 'inline_template',
        '#template' => "
          {% set content %}<div>My Markup<input type='hidden' id='key' value='value' /></div>{%endset %}
          {{ include('ui_patterns_test:test-component', {slot: content}) }}",
        '#context' => [],
      ],
    ];

    foreach ($render_array_tests as $render_array_test) {
      $this->assertExpectedOutput(
        [
          'rendered_value' => '<input ',
          'assert' => 'assertStringContainsString',
        ],
        $render_array_test
      );
      $this->assertExpectedOutput(
        [
          'rendered_value' => '<div>My Markup',
          'assert' => 'assertStringContainsString',
        ],
        $render_array_test
      );
    }
  }

}
