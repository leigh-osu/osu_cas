<?php

namespace Drupal\charts_echarts\Plugin\chart\Library;

use Drupal\charts\Plugin\chart\Library\ChartBase;
use Drupal\Component\Utility\Color;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;

/**
 * Define a concrete class for a Chart.
 *
 * @Chart(
 *   id = "echarts",
 *   name = @Translation("ECharts"),
 *   types = {
 *     "area",
 *     "bar",
 *     "bubble",
 *     "column",
 *     "donut",
 *     "gauge",
 *     "line",
 *     "pie",
 *     "scatter",
 *     "spline",
 *   },
 * )
 */
class Echarts extends ChartBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['placeholder'] = [
      '#title' => $this->t('Placeholder'),
      '#type' => 'fieldset',
      '#description' => $this->t(
        'This is a placeholder for eChart.js-specific library options. If you would like to help build this out, please work from <a href="@issue_link">this issue</a>.', [
          '@issue_link' => Url::fromUri('https://www.drupal.org/project/charts/issues/3046984')
            ->toString(),
        ]),
    ];
    $xaxis_configuration = $this->configuration['xaxis'] ?? [];
    $yaxis_configuration = $this->configuration['yaxis'] ?? [];
    $form['xaxis'] = [
      '#title' => $this->t('X-Axis Settings'),
      '#type' => 'fieldset',
      '#tree' => TRUE,
    ];
    $form['xaxis']['autoskip'] = [
      '#title' => $this->t('Enable autoskip'),
      '#type' => 'checkbox',
      '#default_value' => $xaxis_configuration['autoskip'] ?? 1,
    ];
    $form['xaxis']['label_rotation'] = [
      '#title' => $this->t('Label Rotation'),
      '#type' => 'number',
      '#default_value' => $xaxis_configuration['label_rotation'] ?? 0,
      '#description' => $this->t('Rotate the X-axis labels by the specified degree.'),
    ];
    $form['xaxis']['line_style'] = [
      '#title' => $this->t('Line Style'),
      '#type' => 'select',
      '#options' => [
        'solid' => $this->t('Solid'),
        'dashed' => $this->t('Dashed'),
        'dotted' => $this->t('Dotted'),
      ],
      '#default_value' => $xaxis_configuration['line_style'] ?? 'solid',
    ];
    $form['xaxis']['horizontal_axis_title_align'] = [
      '#title' => $this->t('Align horizontal axis title'),
      '#type' => 'select',
      '#options' => [
        'start' => $this->t('Start'),
        'center' => $this->t('Center'),
        'end' => $this->t('End'),
      ],
      '#default_value' => $xaxis_configuration['horizontal_axis_title_align'] ?? '',
    ];
    $form['yaxis'] = [
      '#title' => $this->t('Y-Axis Settings'),
      '#type' => 'fieldset',
      '#tree' => TRUE,
    ];
    $form['yaxis']['vertical_axis_title_align'] = [
      '#title' => $this->t('Align vertical axis title'),
      '#type' => 'select',
      '#options' => [
        'start' => $this->t('Start'),
        'center' => $this->t('Center'),
        'end' => $this->t('End'),
      ],
      '#default_value' => $yaxis_configuration['vertical_axis_title_align'] ?? '',
    ];

    return $form;
  }

  /**
   * Build configurations.
   *
   * @param array $form
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['xaxis'] = $values['xaxis'];
      $this->configuration['yaxis'] = $values['yaxis'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(array $element) {
    $chart_definition = [];

    if (!isset($element['#id'])) {
      $element['#id'] = Html::getUniqueId('echarts-render');
    }

    $chart_definition = $this->populateDatasets($element, $chart_definition);
    $chart_definition = $this->populateOptions($element, $chart_definition);

    $element['#attached']['library'][] = 'charts_echarts/echarts';
    $element['#attributes']['class'][] = 'charts-echarts';
    $element['#chart_definition'] = $chart_definition;

    return $element;
  }

  /**
   * Populate options.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  private function populateOptions(array $element, array $chart_definition) {
    $chart_type = $this->populateChartType($element);
    $children = Element::children($element);
    $y_axes = [];
    foreach ($children as $child) {
      $type = $element[$child]['#type'];
      if ($type === 'chart_xaxis' && !in_array($chart_type, ['pie', 'doughnut'])) {
        $categories = [];
        if (!empty($element[$child]['#labels'])) {
          $categories = array_map('strip_tags', $element[$child]['#labels']);
        }
        // Merge in axis raw options.
        if (!empty($element[$child]['#raw_options'])) {
          $categories = NestedArray::mergeDeepArray([
            $element[$child]['#raw_options'],
            $categories,
          ]);
        }
        $chart_definition['options']['xAxis']['data'] = $categories;
        if ($chart_type !== 'radar') {
          if (!empty($element[$child]['#title'])) {
            $chart_definition['options']['xAxis']['name'] = $element[$child]['#title'];
          }
        }
        else {
          $radar = new \stdClass();
          $indicators = [];
          foreach ($categories as $category) {
            $indicator = new \stdClass();
            $indicator->name = $category;
            $indicators[] = $indicator;
          }
          $radar->indicator = $indicators;
          $chart_definition['options']['radar'] = $radar;
          unset($chart_definition['options']['xAxis']);
        }
        if ($chart_type === 'scatter') {
          // From the Handbook: However, the normal scene for the scatter
          // chart is to have 2 continuous value axis (also called the
          // cartesian coordinate system). The series type is different in that
          // both x-axis and y-axis value are included in data, but not in
          // xAxis and yAxis.
          unset($chart_definition['options']['xAxis']['data']);
        }
      }
      if ($type === 'chart_yaxis') {
        if ($chart_type !== 'radar') {
          if (!empty($element[$child]['#min'])) {
            $y_axes[$child]['min'] = $element[$child]['#min'];
          }
          if (!empty($element[$child]['#max'])) {
            $y_axes[$child]['max'] = $element[$child]['#max'];
          }
          if (!empty($element[$child]['#title'])) {
            $y_axes[$child]['name'] = $element[$child]['#title'];
          }
        }
        else {
          unset($chart_definition['options']['yAxis']);
        }
      }
    }
    if (!in_array($chart_type, ['pie', 'doughnut'])) {
      $chart_definition['options']['yAxis'] = array_values($y_axes);
    }

    $chart_definition['options']['title'] = $this->buildTitle($element);
    $chart_definition['options']['tooltip'] = $this->buildTooltip($element);
    $chart_definition['options']['toolbox'] = $this->buildToolbox($element);
    $chart_definition['options']['legend'] = $this->buildLegend($element);
    $chart_definition['options']['width'] = $element['#width'] ?? 800;
    $chart_definition['options']['width_units'] = $element['#width_units'] ?? 'px';
    $chart_definition['options']['height'] = $element['#height'] ?? 400;
    $chart_definition['options']['height_units'] = $element['#height_units'] ?? 'px';

    // Adjust grid layout to ensure space for both the legend and the toolbox.
    $chart_definition['options']['grid'] = [
      'top' => 100,
      'right' => ($element['#legend_position'] === 'right') ? 100 : 50,
      'bottom' => ($element['#legend_position'] === 'bottom') ? 100 : 50,
      'left' => 100,
    ];

    // Merge in chart raw options.
    if (!empty($element['#raw_options'])) {
      $chart_definition = NestedArray::mergeDeepArray([
        $chart_definition,
        $element['#raw_options'],
      ]);
    }

    if ($element['#chart_type'] === 'bar') {
      $new_x_axis = $chart_definition['options']['yAxis'];
      $new_y_axis = $chart_definition['options']['xAxis'];
      $chart_definition['options']['yAxis'] = $new_y_axis;
      $chart_definition['options']['xAxis'] = $new_x_axis;
    }

    return $chart_definition;
  }

  /**
   * Populate yAxis array.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   Return the chart y axes.
   */
  private function getYaxisArray(array $element) {
    $y_axes = [];
    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_yaxis') {
        if ($element[$key]['#opposite'] === TRUE) {
          $y_axes[$key] = 1;
        }
        else {
          $y_axes[$key] = 0;
        }
      }
    }

    return $y_axes;
  }

  /**
   * Populate Dataset.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  private function populateDatasets(array $element, array $chart_definition) {
    $chart_type = $this->populateChartType($element);
    $datasets = [];
    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_data') {
        $series_data = [];
        $dataset = new \stdClass();
        // Populate the data.
        foreach ($element[$key]['#data'] as $data_index => $data) {
          if (isset($series_data[$data_index])) {
            $series_data[$data_index][] = $data;
          }
          else {

            /*
             * This is here to account for differences between Views and
             * the API. Will change if someone can find a better way.
             */
            if ($chart_type === 'gauge' && !empty($data[1])) {
              $data = ['value' => $data[1], 'name' => $data[0]];
            }
            if (in_array($chart_type, ['pie', 'doughnut'])) {
              if (!empty($data[1])) {
                $data = ['value' => $data[1], 'name' => $data[0]];
              }
              else {
                // Handle the case where $data[1] is null.
                $data = [
                  'value' => 0,
                  'name' => $data[0],
                ];
              }
              // Assign default colors from $element['#colors'] if available.
              if (empty($chart_definition['options']['color'])) {
                $chart_definition['options']['color'] = $element['#colors'] ?? [];
              }
            }
            $series_data[$data_index] = $data;
          }
        }
        if (!empty($element['#stacking']) && $element['#stacking'] == 1) {
          $dataset->stack = 'total';
        }
        $dataset->name = $element[$key]['#title'];
        $dataset->data = $series_data;
        $series_type = isset($element[$key]['#chart_type']) ? $this->populateChartType($element[$key]) : $chart_type;
        $dataset->type = $series_type;
        if (!empty($element[$key]['#color'])) {
          $dataset->color = $element[$key]['#color'];
        }
        $background_style = new \stdClass();
        $background_style->color = $element[$key]['#color'];
        $dataset->backgroundStyle = $background_style;
        if (in_array($chart_type, ['pie', 'doughnut'])) {
          $dataset->type = 'pie';
          if ($chart_type === 'pie') {
            $dataset->radius = '60%';
          }
          if ($chart_type === 'doughnut') {
            $dataset->radius = ['40%', '70%'];
          }
          // Use x-axis labels as the labels for the slices.
          if (!empty($element['x_axis']['#labels'])) {
            foreach ($dataset->data as $index => &$data_item) {
              if (!empty($element['x_axis']['#labels'][$index])) {
                $data_item['name'] = strip_tags($element['x_axis']['#labels'][$index]);
              }
            }
          }
          // Assign different colors for each slice from $element['#colors'].
          if (!empty($element['#colors'])) {
            foreach ($dataset->data as $index => &$data_item) {
              $data_item['itemStyle'] = [
                'color' => $element['#colors'][$index % count($element['#colors'])],
              ];
            }
          }
        }
        if ($chart_type === 'gauge') {
          $line_style = new \stdClass();
          $line_style->width = 30;
          $line_style->color = [
            [0.3, '#67e0e3'],
            [0.7, '#37a2da'],
            [1, '#fd666d'],
          ];
          $axis_line = new \stdClass();
          $axis_line->lineStyle = $line_style;
          $dataset->axisLine = $axis_line;
          $axis_tick = new \stdClass();
          $axis_tick->distance = -30;
          $axis_tick->length = 8;
          $axis_line_style = new \stdClass();
          $axis_line_style->color = '#fff';
          $axis_line_style->width = 2;
          $axis_tick->lineStyle = $axis_line_style;
          $dataset->axisTick = $axis_tick;
          $split_line = new \stdClass();
          $split_line_line_style = new \stdClass();
          $split_line->distance = -30;
          $split_line->length = 30;
          $split_line_line_style->color = '#fff';
          $split_line_line_style->width = 4;
          $split_line->lineStyle = $split_line_line_style;
          $dataset->splitLine = $split_line;
          $axis_label = new \stdClass();
          $axis_label->color = 'auto';
          $axis_label->distance = 40;
          $axis_label->fontSize = 14;
          $dataset->axisLabel = $axis_label;
        }
        if ((!empty($element[$key]['#chart_type']) && $element[$key]['#chart_type'] === 'area') || (!empty($element['#chart_type']) && $element['#chart_type'] === 'area')) {
          $dataset->type = 'line';
          $background_style = new \stdClass();
          $background_style->color = $this->getTranslucentColor($element[$key]['#color']);
          $dataset->backgroundStyle = $background_style;
          $dataset->areaStyle = new \stdClass();
        }
        if (!empty($element[$key]['#target_axis'])) {
          $y_axes_array = $this->getYaxisArray($element);
          $dataset->yAxisIndex = $y_axes_array[$element[$key]['#target_axis']];
        }
        if (!empty($element['#connect_nulls'])) {
          $dataset->connectNulls = TRUE;
        }

        $datasets[] = $dataset;
      }

      // Merge in axis raw options.
      if (!empty($element[$key]['#raw_options'])) {
        $datasets = NestedArray::mergeDeepArray([
          $datasets,
          $element[$key]['#raw_options'],
        ]);
      }
    }
    if ($chart_type === 'radar' && $datasets) {
      $new_datasets = new \stdClass();
      $combined_dataset = [];
      foreach ($datasets as $dataset) {
        $combined_dataset_item = new \stdClass();
        $combined_dataset_item->name = $dataset->name;
        $combined_dataset_item->value = $dataset->data;
        $combined_dataset[] = $combined_dataset_item;
      }
      $new_datasets->data = $combined_dataset;
      $new_datasets->type = 'radar';
      $datasets = $new_datasets;
    }
    $chart_definition['options']['series'] = $datasets;

    return $chart_definition;
  }

  /**
   * Outputs a type that can be used by Chart.js.
   *
   * @param array $element
   *   The given element.
   *
   * @return string
   *   The generated type.
   */
  protected function populateChartType(array $element) {
    switch ($element['#chart_type']) {
      case 'bar':

      case 'column':
        $type = 'bar';
        break;

      case 'area':

      case 'spline':
        $type = 'line';
        break;

      case 'donut':
        $type = 'doughnut';
        break;

      case 'gauge':
        $type = 'gauge';
        break;

      default:
        $type = $element['#chart_type'];
        break;
    }
    if (isset($element['#polar']) && $element['#polar'] == 1) {
      $type = 'radar';
    }

    return $type;
  }

  /**
   * Builds legend based on element properties.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The legend array.
   */
  protected function buildLegend(array $element) {
    $chart_type = $this->populateChartType($element);
    $legend = [];
    // Configure the legend display.
    $legend['show'] = (bool) $element['#legend'];

    if ($element['#legend_position'] === 'right') {
      $legend['right'] = 0;
      $legend['top'] = 'center';
      $legend['orient'] = 'vertical';
    }
    elseif ($element['#legend_position'] === 'bottom') {
      $legend['bottom'] = 0;
      $legend['left'] = 'center';
      $legend['orient'] = 'horizontal';
    }
    else {
      $legend[$element['#legend_position']] = 0;
      $legend['orient'] = 'horizontal';
    }
    $legend['type'] = 'scroll';

    // Configure legend position.
    if (!empty($element['#legend_position'])) {
      if (!empty($element['#legend_font_weight'])) {
        $legend['textStyle']['fontWeight'] = $element['#legend_font_weight'];
      }
      if (!empty($element['#legend_font_style'])) {
        $legend['textStyle']['fontStyle'] = $element['#legend_font_style'];
      }
      if (!empty($element['#legend_font_size'])) {
        $legend['textStyle']['fontSize'] = $element['#legend_font_size'];
      }
    }

    return $legend;
  }

  /**
   * Builds tooltip based on element properties.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The tooltip array.
   */
  protected function buildTooltip(array $element) {
    $chart_type = $this->populateChartType($element);
    // Configure the tooltip display.
    $tooltip = [];
    $tooltip['show'] = (bool) $element['#tooltips'];

    if (in_array($chart_type, ['pie', 'doughnut'])) {
      $tooltip['trigger'] = 'item';
    }
    else {
      $tooltip['trigger'] = 'axis';
      $tooltip['showContent'] = TRUE;
      $tooltip['alwaysShowContent'] = TRUE;
      $tooltip['axisPointer'] = [
        'label' => [
          'show' => FALSE,
        ],
      ];
    }

    $tooltip['triggerOn'] = 'mousemove';
    $tooltip['textStyle']['fontSize'] = '10';

    return $tooltip;
  }

  /**
   * Builds toolbox based on element properties.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The toolbox array.
   */
  protected function buildToolbox(array $element) {
    $toolbox = [];
    // Configure the toolbox display.
    $toolbox['show'] = TRUE;
    $toolbox['feature'] = [
      'mark' => [
        'show' => TRUE,
      ],
      'dataZoom' => [
        'yAxisIndex' => 'none',
      ],
      'magicType' => [
        'show' => TRUE,
        'type' => ['line', 'bar'],
        'title' => [
          'line' => 'Line',
          'bar' => 'Bar',
        ],
      ],
      'restore' => [
        'show' => TRUE,
        'title' => 'Reset',
      ],
      'saveAsImage' => [
        'show' => TRUE,
        'title' => 'Save',
      ],
    ];

    return $toolbox;
  }

  /**
   * Builds title based on element properties.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The title array.
   */
  protected function buildTitle(array $element) {
    $title = [];
    if (!empty($element['#title'])) {
      $title = [
        'show' => TRUE,
        'text' => $element['#title'],
      ];
      if (!empty($element['#subtitle'])) {
        $title['subtext'] = $element['#subtitle'];
      }
      if (!empty($element['#title_position'])) {
        if (in_array($element['#title_position'], ['in', 'out'])) {
          $title['top'] = 'auto';
        }
        else {
          $title[$element['#title_position']] = 'auto';
        }
      }
      if (!empty($element['#title_color'])) {
        $title['textStyle']['color'] = $element['#title_color'];
      }
      if (!empty($element['#title_font_weight'])) {
        $title['textStyle']['fontWeight'] = $element['#title_font_weight'];
      }
      if (!empty($element['#title_font_style'])) {
        $title['textStyle']['fontStyle'] = $element['#title_font_style'];
      }
      if (!empty($element['#title_font_size'])) {
        $title['textStyle']['fontSize'] = $element['#title_font_size'];
      }
    }
    else {
      // Ensure title is an array even if empty.
      $title = [
        'show' => FALSE,
        'text' => '',
      ];
    }

    return $title;
  }

  /**
   * Get translucent color.
   *
   * @param string $color
   *   The color.
   *
   * @return string
   *   The color.
   */
  protected function getTranslucentColor($color) {
    if (!$color) {
      return '';
    }
    $rgb = Color::hexToRgb($color);

    return 'rgba(' . implode(",", $rgb) . ',' . 0.5 . ')';
  }

}
