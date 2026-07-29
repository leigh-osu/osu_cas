<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ui_patterns\Traits\TestDataTrait;

/**
 * Base class to test prop type normalization.
 */
abstract class PropTypeNormalizationTestBase extends KernelTestBase {

  use TestDataTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'text',
    'field',
    'node',
    'ui_patterns',
    'ui_patterns_test',
    'datetime',
    'filter',
  ];

  /**
   * Props of the test-component Component.
   */
  protected ?array $testComponentProps;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'filter']);
    $component = \Drupal::service('plugin.manager.sdc')->find('ui_patterns_test:test-component');
    $this->testComponentProps = $component->metadata->schema['properties'] ?? [];
  }

  /**
   * Run tests with a given prop.
   *
   * @param string|null $prop
   *   The prop to test or NULL for no prop.
   * @param array $tested_value
   *   The tested value.
   * @param string $message
   *   An optional message to identify the data set on failure.
   */
  protected function runRenderPropTest(?string $prop, array $tested_value, string $message = ''): void {
    $exception_class = $tested_value['exception_class'] ?? NULL;

    if (!empty($exception_class)) {
      // Data providers have been dropped to avoid re-installing Drupal for
      // each data set (see #3605477). As several data sets now run inside a
      // single test method, the expected exception is caught explicitly
      // instead of relying on expectException(), which would abort the
      // remaining data sets of the test method.
      $thrown = NULL;

      try {
        $this->doRenderPropTest($prop, $tested_value, $message);
      }
      catch (\Throwable $exception) {
        $thrown = $exception;
      }
      $this->assertInstanceOf($exception_class, $thrown, $message);

      return;
    }
    $this->doRenderPropTest($prop, $tested_value, $message);
  }

  /**
   * Render and assert a single prop value.
   *
   * @param string|null $prop
   *   The prop to test or NULL for no prop.
   * @param array $tested_value
   *   The tested value.
   * @param string $message
   *   An optional message to identify the data set on failure.
   */
  private function doRenderPropTest(?string $prop, array $tested_value, string $message = ''): void {
    if ($tested_value['value'] === NULL && !empty($prop)) {
      // If the value injected is NULL
      // we will also try with removing the prop.
      $this->doRenderPropTest(NULL, $tested_value, $message);
    }
    $expectedOutput = [
      'rendered_value' => $tested_value['rendered_value'],
      'assert' => $tested_value['assert'] ?? 'assertStringContainsString',
    ];

    if (!empty($prop)) {
      $this->assertExpectedOutput($expectedOutput, [
        '#type' => 'inline_template',
        '#template' => \sprintf("{{ include('ui_patterns_test:test-component', {%s: injected}) }}", $prop),
        '#context' => ['injected' => $tested_value['value']],
      ], $message);
    }
    else {
      $this->assertExpectedOutput($expectedOutput, [
        '#type' => 'inline_template',
        '#template' => "{{ include('ui_patterns_test:test-component', {}) }}",
      ], $message);
    }

    if (!empty($prop)) {
      $this->assertExpectedOutput($expectedOutput, [
        '#type' => 'component',
        '#component' => 'ui_patterns_test:test-component',
        '#props' => [$prop => $tested_value['value']],
      ], $message);
    }
    else {
      $this->assertExpectedOutput($expectedOutput, [
        '#type' => 'component',
        '#component' => 'ui_patterns_test:test-component',
      ], $message);
    }
  }

}
