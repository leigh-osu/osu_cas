<?php

namespace Drupal\charts_apexcharts_api_example\Controller;

use Drupal\charts_api_example\ChartExampleBuilder;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Charts API examples rendered with the ApexCharts library.
 *
 * The library-agnostic examples come from the shared ChartExampleBuilder; this
 * controller adds the ApexCharts-specific demonstrations.
 */
class ApexchartsApiExample extends ControllerBase {

  /**
   * Constructs the controller.
   *
   * @param \Drupal\charts_api_example\ChartExampleBuilder $exampleBuilder
   *   The shared chart example builder.
   */
  public function __construct(protected ChartExampleBuilder $exampleBuilder) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('charts_api_example.builder'));
  }

  /**
   * Displays the ApexCharts examples.
   *
   * @return array
   *   A render array.
   */
  public function display(): array {
    $library = 'apexcharts';
    $build = $this->exampleBuilder->build($library);

    // Radar chart: ApexCharts renders the polar display setting (a line chart
    // with #polar) as a radar.
    $build['content']['radar'] = $this->exampleBuilder->buildPolarExample($library);

    // Candlestick chart. ApexCharts expects each point as
    // [open, high, low, close].
    $build['content']['candlestick'] = $this->exampleBuilder->buildCandlestickExample($library, [
      [20, 38, 10, 34],
      [40, 50, 30, 35],
      [31, 44, 33, 38],
      [38, 42, 5, 15],
    ]);

    // Boxplot chart. ApexCharts expects each point as
    // [min, Q1, median, Q3, max].
    $build['content']['boxplot'] = $this->exampleBuilder->buildBoxplotExample($library, [
      [1, 2, 3, 4, 5],
      [2, 3, 4, 5, 6],
      [3, 4, 5, 6, 7],
      [4, 5, 6, 7, 8],
    ]);

    // Range area chart. ApexCharts expects each point as [low, high].
    $build['content']['range_area'] = $this->exampleBuilder->buildRangeAreaExample($library, [
      [6, 10],
      [5, 7],
      [3, 7],
      [4, 9],
    ]);

    // Heatmap chart. ApexCharts builds one series per data row, with the
    // x-axis labels as columns.
    $build['content']['heatmap'] = [
      '#type' => 'chart',
      '#chart_library' => $library,
      '#title' => $this->t('ApexCharts Heatmap Chart'),
      '#chart_type' => 'heatmap',
      'row_one' => [
        '#type' => 'chart_data',
        '#title' => $this->t('Row 1'),
        '#data' => [10, 20, 30, 40],
      ],
      'row_two' => [
        '#type' => 'chart_data',
        '#title' => $this->t('Row 2'),
        '#data' => [15, 25, 35, 45],
      ],
      'row_three' => [
        '#type' => 'chart_data',
        '#title' => $this->t('Row 3'),
        '#data' => [5, 15, 25, 35],
      ],
      'x_axis' => $this->exampleBuilder->defaultXaxis(),
      'y_axis' => $this->exampleBuilder->defaultYaxis(),
      '#accessible_table' => 'collapsible',
      '#raw_options' => [],
    ];

    // Note: ApexCharts also declares "rangebar" and "treemap" support. Add
    // examples for them here once their data shapes are verified on a running
    // site, following the same per-module pattern.
    return $build;
  }

}
