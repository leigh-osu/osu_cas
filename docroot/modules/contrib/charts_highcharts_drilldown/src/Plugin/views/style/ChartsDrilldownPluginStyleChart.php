<?php

namespace Drupal\charts_highcharts_drilldown\Plugin\views\style;

use Drupal\charts\Plugin\views\style\ChartsPluginStyleChart;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Style plugin to render view as a chart.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "chart_highcharts_drilldown",
 *   title = @Translation("Chart highcharts drilldown"),
 *   help = @Translation("Render a chart with drilldown."),
 *   theme = "views_view_chart_highcharts_drilldown",
 *   display_types = { "normal" }
 * )
 */
class ChartsDrilldownPluginStyleChart extends ChartsPluginStyleChart {


  /**
   * List of the supported chart types for the drilldown functionality.
   *
   * @var array|string[]
   */
  protected array $supportedChartTypes = [
    'bar',
    'column',
    'donut',
    'pie',
  ];

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['fields_series_field'] = ['default' => ''];
    $options['fields_drilldown_series_field'] = ['default' => ''];
    $options['fields_data_field'] = ['default' => ''];
    $options['fields_operator'] = ['default' => 'sum'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $data_options = $this->displayHandler->getFieldLabels();
    // Reduce chart type options.
    unset($form['grouping']);
    $form['fields_series_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Series Field'),
      '#options' => $data_options,
      '#weight' => -6,
      '#default_value' => $this->options['fields_series_field'],
      '#description' => $this->t('Select the series field.'),
      '#required' => TRUE,
    ];
    $form['fields_drilldown_series_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Drilldown Field'),
      '#options' => $data_options,
      '#weight' => -5,
      '#default_value' => $this->options['fields_drilldown_series_field'],
      '#description' => $this->t('Select the drilldown field.'),
      '#required' => TRUE,
    ];
    $form['fields_data_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Data Field'),
      '#options' => $data_options,
      '#weight' => -4,
      '#default_value' => $this->options['fields_data_field'],
      '#description' => $this->t('Select the data field (this data will be aggregated for the parent chart).'),
      '#required' => TRUE,
    ];
    $form['fields_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Operator Field'),
      '#options' => ['sum' => 'SUM', 'average' => 'AVERAGE'],
      '#weight' => -3,
      '#default_value' => $this->options['fields_operator'],
      '#description' => $this->t('Select the operator for the aggregation method.'),
    ];
    $form_state->set('default_options', $this->options);
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $chart_settings = $form_state->getValue([
      'style_options',
      'chart_settings',
    ]);
    if (!in_array($chart_settings['type'], $this->supportedChartTypes)) {
      $form_state->setError($form['chart_settings'], $this->t('The Chart highcharts drilldown views style does not support the selected @chart chart type. Only the following chart types are supported: @chart_types.', [
        '@chart_types' => implode(', ', $this->supportedChartTypes),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $errors = parent::validate();

    $chart_settings = $this->options['chart_settings'];
    if (!in_array($chart_settings['type'], $this->supportedChartTypes)) {
      $errors[] = $this->t('The Chart highcharts drilldown views style does not support the selected @chart chart type. Only the following chart types are supported: @chart_types.', [
        '@chart_types' => implode(', ', $this->supportedChartTypes),
      ]);
    }
    $option_labels = [
      'fields_series_field' => $this->t('Series Field'),
      'fields_drilldown_series_field' => $this->t('Drilldown Field'),
      'fields_data_field' => $this->t('Data Field'),
      'fields_operator' => $this->t('Operator'),
    ];
    foreach ($option_labels as $key => $label) {
      if (empty($this->options[$key])) {
        $errors[] = $this->t('The option "@key" is required by Chart highcharts drilldown views style to render the chart.', ['@key' => $label]);
      }
    }

    if (!$errors && !empty($this->options['fields_data_field'])) {
      $data_field = $this->options['fields_data_field'];
      $selected_data_fields = array_keys($this->getSelectedDataFields($chart_settings['fields']['data_providers'] ?? []));
      $field_options = $this->displayHandler->getFieldLabels();
      if (!in_array($data_field, $selected_data_fields)) {
        $errors[] = $this->t('Please ensure that @field (@key) is also selected as data provider in the chart settings.', [
          '@field' => $field_options[$data_field],
          '@key' => $data_field,
        ]);
      }
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $chart_settings = $this->options['chart_settings'];
    if (!in_array($chart_settings['type'], $this->supportedChartTypes)) {
      throw new \LogicException(sprintf('The provided chart type "%s" is not supported by the Chart highcharts drilldown views style. Only the following chart types are supported: "%s"', $chart_settings['type'], implode(', ', $this->supportedChartTypes)));
    }

    $parent_field = $this->options['fields_series_field'];
    $drilldown_field = $this->options['fields_drilldown_series_field'];
    $operator = $this->options['fields_operator'];
    $data_field = $this->options['fields_data_field'];
    $selected_data_fields = array_keys($this->getSelectedDataFields($chart_settings['fields']['data_providers']));
    if (!in_array($this->options['fields_data_field'], $selected_data_fields)) {
      $field_options = $this->displayHandler->getFieldLabels();
      throw new \LogicException(sprintf('Please ensure that %s (%s) is also selected as data provider in the chart settings.', $field_options[$data_field], $data_field));
    }

    $chart = parent::render();
    // The chart display's title settings added to raw options.
    $chart['#raw_options']['title']['text'] = $chart_settings['display']['title'];

    // Skip rendering fields in case parent chart style have already done it.
    if (!isset($this->rendered_fields)) {
      $this->renderFields($this->view->result);
    }
    $renders = $this->rendered_fields;

    $drilldown_series = [];
    $main_chart_series = [];
    $series_color_index = 0;
    $display_colors_count = count($chart_settings['display']['colors']);
    // Build drilldown series.
    foreach ($renders as $row_number => $row) {
      $drilldown_name = $this->processNumberValueFromField($row_number, $parent_field);
      $drilldown_id = Html::getId($drilldown_name);
      $drilldown_value = $this->processNumberValueFromField($row_number, $data_field) + 0;
      // Instantiate drilldown data if not done yet.
      if (empty($drilldown_data[$drilldown_id])) {
        $drilldown_data[$drilldown_id] = [];
      }
      // Instantiate drilldown series variable if not done yet.
      if (empty($drilldown_series[$drilldown_id])) {
        $drilldown_series[$drilldown_id] = [
          'name' => $drilldown_name,
          'id' => $drilldown_id,
          'data' => [],
        ];
      }
      // Instantiate drilldown series variable if not done yet.
      if (empty($main_chart_series[$drilldown_id])) {
        $main_chart_series[$drilldown_id] = [
          'name' => $drilldown_name,
          'y' => 0,
          'drilldown' => $drilldown_id,
          'color' => $chart_settings['display']['colors'][$series_color_index],
        ];
        $series_color_index++;
        // Reset the color index to start from zero to be able to pick again
        // from the assigned colors.
        $series_color_index = $series_color_index === $display_colors_count ? 0 : $series_color_index;
      }

      $drilldown_data[$drilldown_id][] = $drilldown_value;
      $drilldown_series[$drilldown_id]['data'][] = [
        $this->processNumberValueFromField($row_number, $drilldown_field),
        $drilldown_value,
      ];
      $main_chart_series[$drilldown_id]['y'] = $this->calculateMainSeriesYaxisValue($operator, $drilldown_data[$drilldown_id]);
    }
    // Adding drilldown related settings under raw options.
    $chart['#raw_options']['drilldown']['series'] = array_values($drilldown_series);

    // Build the main chart series.
    $chart['#raw_options']['series'] = [
      [
        'name' => !empty($this->view->field[$parent_field]->options['label']) ? $this->view->field[$parent_field]->options['label'] : t('Series'),
        'data' => array_values($main_chart_series),
      ],
    ];
    // Ensure that the series shows in the header and the point is associated with the color.
    $chart['#raw_options']['tooltip']['headerFormat'] = '<span style="font-size: 0.9em">{series.name}</span><br>';
    $chart['#raw_options']['tooltip']['pointFormat'] =  '<span style="color:{point.color}">●</span> <span>{point.name}</span>: <b>{point.y}</b>';

    // Remove chart_data so that highcharts doesn't end up adding them as
    // the main chart series.
    foreach (Element::children($chart) as $key) {
      $children_element_type = $chart[$key]['#type'] ?? '';
      if ($children_element_type === 'chart_data' && empty($chart[$key]['#chart_attachment_id'])) {
        unset($chart[$key]);
      }
    }

    return $chart;
  }

  /**
   * Calculates the main series Yaxis value.
   *
   * @param string $aggregation_function
   *   The drilldown aggregation function.
   * @param array $drilldown_data
   *   The drilldown data collected so far.
   *
   * @return int|float
   *   The calculated value.
   */
  protected function calculateMainSeriesYaxisValue(string $aggregation_function, array $drilldown_data): int|float {
    if (!in_array($aggregation_function, ['sum', 'avg', 'average'])) {
      return 0;
    }

    $value = array_sum($drilldown_data);
    if ($aggregation_function !== 'sum') {
      $chart_settings = $this->options['chart_settings'];
      $precision = !empty($chart_settings['yaxis']['decimal_count']) ? $chart_settings['yaxis']['decimal_count'] : 0;
      $denominator = count($drilldown_data);
      $value = $denominator > 0 ? round($value / $denominator, $precision) : 0;
    }

    return $value;
  }

  /**
   * Processes number value based on field.
   *
   * @param int $number
   *   The number.
   * @param string $field
   *   The field.
   *
   * @return string|null
   *   The value.
   */
  public function processNumberValueFromField($number, $field) {
    $value = trim(strip_tags((string) $this->getField($number, $field)));
    if ($value === '' || is_null($value)) {
      return NULL;
    }
    return $value;
  }

}
