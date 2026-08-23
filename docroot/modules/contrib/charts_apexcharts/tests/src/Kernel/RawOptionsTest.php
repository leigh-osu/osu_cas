<?php

namespace Drupal\Tests\charts_apexcharts\Kernel;

use Drupal\Tests\charts\Kernel\ChartsKernelTestBase;

/**
 * Test the raw_options element property behavior.
 *
 * @group charts
 */
class RawOptionsTest extends ChartsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'charts',
    'charts_apexcharts',
  ];

  /**
   * Test raw options set on the main chart element.
   */
  public function testMergeDeepArrayInPopulateOptions() {
    $series = [
      '#type' => 'chart_data',
      '#title' => '5.0.x',
      '#data' => [257, 235, 325, 340],
      '#color' => '#1d84c3',
    ];
    $element = [
      '#type' => 'chart',
      '#chart_type' => 'line',
      '#chart_library' => 'apexcharts',
      'series' => $series,
      '#raw_options' => [
        'plotOptions' => [
          'line' => ['isSlopeChart' => TRUE],
        ],
      ],
    ];

    // Testing raw options when there is NO data already set in the definition.
    $path = ['plotOptions', 'line', 'isSlopeChart'];
    $this->assertJsonPropertyHasValue($element, $path, TRUE);

    // Testing raw options when there is data already set in the definition.
    $element['#data_labels'] = TRUE;
    $element['#raw_options'] = [
      'dataLabels' => [
        'enabled' => FALSE,
      ],
    ];
    $path = ['dataLabels', 'enabled'];
    $this->assertJsonPropertyHasValue($element, $path, FALSE);
  }

}
