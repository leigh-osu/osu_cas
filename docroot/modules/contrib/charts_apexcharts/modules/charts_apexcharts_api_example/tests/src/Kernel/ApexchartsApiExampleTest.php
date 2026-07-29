<?php

namespace Drupal\Tests\charts_apexcharts_api_example\Kernel;

use Drupal\charts_apexcharts_api_example\Controller\ApexchartsApiExample;
use Drupal\Tests\charts\Kernel\ChartsKernelTestBase;

/**
 * Tests the ApexCharts API example page.
 *
 * ApexCharts' page is the shared builder output plus the ApexCharts-specific
 * extras (radar, candlestick, boxplot, range_area, heatmap) added in the
 * controller.
 *
 * @group charts
 */
class ApexchartsApiExampleTest extends ChartsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'charts',
    'charts_api_example',
    'charts_apexcharts',
    'charts_apexcharts_api_example',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(['charts']);
  }

  /**
   * Tests the example keys exposed for ApexCharts.
   */
  public function testExampleKeys() {
    $build = ApexchartsApiExample::create($this->container)->display();
    $keys = array_keys($build['content'] ?? []);

    // Generic examples for the types ApexCharts declares.
    foreach ([
      'area', 'bar', 'column', 'line', 'spline', 'pie', 'donut',
      'two_series_column', 'stacked_two_series_column', 'combo',
      'combo_dual_yaxes', 'from_csv_file', 'gauge', 'scatter', 'bubble',
    ] as $key) {
      $this->assertContains($key, $keys, "ApexCharts should expose the '$key' example.");
    }

    // ApexCharts-specific extras added by the controller.
    foreach (['radar', 'candlestick', 'boxplot', 'range_area', 'heatmap'] as $key) {
      $this->assertContains($key, $keys, "ApexCharts should expose the '$key' example.");
    }

    // Examples belonging to other libraries must not appear here.
    foreach (['polar_area', 'php_override', 'js_override'] as $key) {
      $this->assertNotContains($key, $keys, "ApexCharts should not expose the '$key' example.");
    }

    // ApexCharts declares "treemap" and "rangebar" but those examples are not
    // demoed yet; pin that so adding them is a deliberate, tested change.
    foreach (['treemap', 'rangebar'] as $key) {
      $this->assertNotContains($key, $keys, "The '$key' example is not implemented yet.");
    }
  }

}
