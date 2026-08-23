<?php

namespace Drupal\charts_apexcharts\Plugin\chart\Library;

use Drupal\charts\Attribute\Chart;
use Drupal\charts\Element\Chart as ChartElement;
use Drupal\charts\Plugin\chart\Library\ChartBase;
use Drupal\charts\TypeManager;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The 'Apexcharts.js' chart library attribute.
 */
#[Chart(
  id: "apexcharts",
  name: new TranslatableMarkup("Apexcharts"),
  types: [
    "area",
    "arearange",
    "bar",
    "boxplot",
    "bubble",
    "candlestick",
    "column",
    "donut",
    "gauge",
    "heatmap",
    "line",
    "pie",
    "radar",
    "rangebar",
    "scatter",
    "spline",
    "treemap",
  ],
  example_route: "charts_apexcharts_api_example.display",
)]
class Apexcharts extends ChartBase implements ContainerFactoryPluginInterface {

  /**
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfo;

  /**
   * The chart type manager.
   *
   * @var \Drupal\charts\TypeManager
   */
  protected $chartTypeManager;

  /**
   * The chart type manager.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a \Drupal\views\Plugin\Block\ViewsBlockBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info
   *   The element info manager.
   * @param \Drupal\charts\TypeManager $chart_type_manager
   *   The chart type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $module_handler
   *   The module handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ElementInfoManagerInterface $element_info, TypeManager $chart_type_manager, FormBuilderInterface $form_builder, ?ModuleHandlerInterface $module_handler = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $module_handler, $form_builder);
    $this->elementInfo = $element_info;
    $this->chartTypeManager = $chart_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('element_info'),
      $container->get('plugin.manager.charts_type'),
      $container->get('form_builder'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function addBaseSettingsElementOptions(array &$element, array $options, FormStateInterface $form_state, array &$complete_form = []): void {
    // Applies to all chart types.
    $element['enable_sparkline'] = [
      '#title' => $this->t('Enable sparkline'),
      '#type' => 'checkbox',
      '#default_value' => !empty($options['enable_sparkline']),
      '#description' => $this->t('Enable to hide all the elements other than the primary paths.'),
    ];

    $element['enable_dark_mode'] = [
      '#title' => $this->t('Enable "dark" theme'),
      '#type' => 'checkbox',
      '#default_value' => !empty($options['enable_dark_mode']),
      '#description' => $this->t('Enable dark mode for the charts.'),
    ];

    // Settings for bar and column.
    if (in_array($element['#chart_type'], ['bar', 'column'])) {
      $element['enable_stack_totals'] = [
        '#title' => $this->t('Enable stack totals'),
        '#type' => 'checkbox',
        '#default_value' => !empty($options['enable_stack_totals']),
        '#description' => $this->t('Enable stack totals for stacked bar or column charts.'),
      ];
      $element['enable_dumbbell'] = [
        '#title' => $this->t('Enable dumbbell'),
        '#type' => 'checkbox',
        '#default_value' => !empty($options['enable_dumbbell']),
        '#description' => $this->t('Enable dumbbell for bar or column charts.'),
      ];
      // Minimum color value.
      $element['min_color'] = [
        '#title' => $this->t('Minimum color'),
        '#type' => 'textfield',
        '#size' => 10,
        '#maxlength' => 7,
        '#attributes' => [
          'placeholder' => '#FFFFFF',
          'TYPE' => 'color',
        ],
        '#description' => $this->t('The color to use for the minimum value. Leave blank for no minimum color.'),
        '#default_value' => $options['min_color'] ?? '#FFFFFF',
      ];
      // Maximum color value.
      $element['max_color'] = [
        '#title' => $this->t('Maximum color'),
        '#type' => 'textfield',
        '#size' => 10,
        '#maxlength' => 7,
        '#attributes' => [
          'placeholder' => '#000000',
          'TYPE' => 'color',
        ],
        '#description' => $this->t('The color to use for the maximum value. Leave blank for no maximum color.'),
        '#default_value' => $options['max_color'] ?? '#000000',
      ];
    }
    elseif ($element['#chart_type'] === 'line') {
      // Is chart a slope chart?
      $element['enable_slope_chart'] = [
        '#title' => $this->t('Enable slope chart'),
        '#type' => 'checkbox',
        '#default_value' => !empty($options['enable_slope_chart']),
        '#description' => $this->t('Enable the slope chart feature for line charts.'),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(array $element) {
    $chart_definition = [];

    if (!isset($element['#id'])) {
      $element['#id'] = Html::getUniqueId('apexcharts-render');
    }
    $chart_definition = $this->populateOptions($element, $chart_definition);
    $chart_definition = $this->populateAxes($element, $chart_definition);
    $chart_definition = $this->populateData($element, $chart_definition);

    $element['#attached']['library'][] = 'charts_apexcharts/apexcharts';
    $element['#attributes']['class'][] = 'charts-apexcharts';
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
  protected function populateOptions(array $element, array $chart_definition) {
    $chart_type = $this->chartTypeConversion($element['#chart_type']);

    // Chart type specific settings.
    if ($element['#chart_type'] === 'bar') {
      $chart_definition['plotOptions']['bar']['horizontal'] = TRUE;
    }
    if ($chart_type === 'line' && !empty($element['#polar'])) {
      $chart_type = 'radar';
    }
    if ($element['#chart_type'] === 'spline') {
      $chart_definition['stroke']['curve'] = 'smooth';
    }

    // Basic chart configuration.
    $chart_definition['chart']['type'] = $chart_type;
    $chart_definition['chart']['stacked'] = (bool) $element['#stacking'];

    // 3D effects if enabled.
    if (!empty($element['#three_dimensional'])) {
      $chart_definition['chart']['toolbar']['show'] = TRUE;
      $chart_definition['chart']['zoom']['enabled'] = TRUE;

      if ($chart_type === 'bar' || $chart_type === 'column') {
        $chart_definition['plotOptions']['bar']['distributed'] = TRUE;
        $chart_definition['plotOptions']['bar']['columnWidth'] = '70%';
        $chart_definition['chart']['dropShadow'] = [
          'enabled' => TRUE,
          'top' => 5,
          'left' => 5,
          'blur' => 10,
          'opacity' => 0.35,
        ];
      }
    }

    // Enable column totals for stacked bar or column charts.
    if (!empty($element['#library_type_options']['enable_stack_totals'])) {
      $chart_definition['plotOptions']['bar']['dataLabels']['total']['enabled'] = TRUE;
    }

    // Enable slope chart feature for line charts.
    if (!empty($element['#library_type_options']['enable_slope_chart'])) {
      $chart_definition['plotOptions']['line']['isSlopeChart'] = TRUE;
    }

    // Enable sparkline if requested.
    if (!empty($element['#library_type_options']['enable_sparkline'])) {
      $chart_definition['chart']['sparkline'] = [
        'enabled' => TRUE,
      ];
    }

    // Chart dimensions.
    if (!empty($element['#width'])) {
      $chart_definition['chart']['width'] = $element['#width'] . ($element['#width_units'] ?? 'px');
    }
    if (!empty($element['#height'])) {
      $chart_definition['chart']['height'] = $element['#height'] . ($element['#height_units'] ?? 'px');
    }

    // Chart background.
    if (!empty($element['#background'])) {
      $chart_definition['chart']['background'] = $element['#background'];
    }

    // Title configuration.
    $chart_definition['title']['text'] = $element['#title'] ?? '';
    $chart_definition['subtitle']['text'] = $element['#subtitle'] ?? '';

    // Title position.
    if (!empty($element['#title_position'])) {
      $position_map = [
        'outside' => 'center',
        'top' => 'center',
        'left' => 'left',
        'right' => 'right',
        'bottom' => 'center',
      ];

      $chart_definition['title']['align'] = $position_map[$element['#title_position']] ?? 'center';
      $chart_definition['subtitle']['align'] = $position_map[$element['#title_position']] ?? 'center';
    }

    // Title styling.
    if (!empty($element['#title_color'])) {
      $chart_definition['title']['style']['color'] = $element['#title_color'];
    }

    if (!empty($element['#title_font_size'])) {
      $chart_definition['title']['style']['fontSize'] = $element['#title_font_size'] . 'px';
    }

    if (!empty($element['#title_font_weight'])) {
      $chart_definition['title']['style']['fontWeight'] = $element['#title_font_weight'];
    }

    if (!empty($element['#title_font_style']) && $element['#title_font_style'] === 'italic') {
      $chart_definition['title']['style']['fontStyle'] = 'italic';
    }

    // Font family.
    if (!empty($element['#font'])) {
      $chart_definition['chart']['fontFamily'] = $element['#font'];

      // Apply font to all text elements.
      $chart_definition['title']['style']['fontFamily'] = $element['#font'];
      $chart_definition['subtitle']['style']['fontFamily'] = $element['#font'];
    }

    // Font size.
    if (!empty($element['#font_size'])) {
      $chart_definition['chart']['defaultFontSize'] = (int) $element['#font_size'];
    }

    // Tooltip configuration.
    if (isset($element['#tooltips'])) {
      $chart_definition['tooltip']['enabled'] = (bool) $element['#tooltips'];

      if (isset($element['#tooltips_use_html']) && $element['#tooltips_use_html']) {
        // @todo Implement custom tooltip rendering.
      }

      $chart_definition['tooltip']['style'] = [
        'fontSize' => !empty($element['#font_size']) ? $element['#font_size'] . 'px' : '12px',
      ];
    }

    // Data labels configuration.
    if (isset($element['#data_labels'])) {
      $chart_definition['dataLabels']['enabled'] = (bool) $element['#data_labels'];

      if (!empty($element['#font_size'])) {
        $chart_definition['dataLabels']['style']['fontSize'] = $element['#font_size'] . 'px';
      }
    }

    // Data markers configuration.
    if (isset($element['#data_markers'])) {
      $chart_definition['markers']['size'] = $element['#data_markers'] ? 6 : 0;
      $chart_definition['markers']['strokeWidth'] = 2;
      $chart_definition['markers']['hover']['size'] = $element['#data_markers'] ? 8 : 0;
    }

    // Dumbbell configuration if enabled.
    if (!empty($element['#library_type_options']['enable_dumbbell'])) {
      $chart_definition['markers']['size'] = $element['#data_markers'] ? 12 : 0;
      $chart_definition['plotOptions']['bar']['isDumbbell'] = TRUE;
      $chart_definition['plotOptions']['bar']['dumbbellColors'] = [
        [
          $element['#library_type_options']['min_color'],
          $element['#library_type_options']['max_color'],
        ],
      ];
      $chart_definition['fill'] = [
        'type' => 'gradient',
        'gradient' => [
          'type' => 'vertical',
          'gradientToColors' => [
            $element['#library_type_options']['max_color'],
          ],
          'inverseColors' => TRUE,
        ],
      ];
    }

    // Legend configuration.
    if (isset($element['#legend'])) {
      $chart_definition['legend']['show'] = (bool) $element['#legend'];

      // Legend position.
      if (!empty($element['#legend_position'])) {
        $legend_position_map = [
          'top' => 'top',
          'right' => 'right',
          'bottom' => 'bottom',
          'left' => 'left',
        ];
        $chart_definition['legend']['position'] = $legend_position_map[$element['#legend_position']] ?? 'bottom';

        // For top and bottom positions, set horizontal alignment.
        if (in_array($element['#legend_position'], ['top', 'bottom'])) {
          $chart_definition['legend']['horizontalAlign'] = 'center';
        }
      }

      // Legend title.
      if (!empty($element['#legend_title'])) {
        // ApexCharts doesn't have a direct legend title property.
        // Using itemMargin as a workaround to create space for a custom title.
        $chart_definition['legend']['itemMargin'] = [
          'horizontal' => 5,
          'vertical' => 20,
        ];
      }

      // Legend styling.
      $chart_definition['legend']['fontSize'] = !empty($element['#legend_font_size'])
        ? $element['#legend_font_size'] . 'px'
        : ($element['#font_size'] ?? '12') . 'px';

      if (!empty($element['#legend_font_weight'])) {
        $chart_definition['legend']['fontWeight'] = $element['#legend_font_weight'];
      }

      if (!empty($element['#legend_font_style'])) {
        $chart_definition['legend']['fontStyle'] = $element['#legend_font_style'];
      }
    }

    // Colors.
    if (!empty($element['#colors']) && is_array($element['#colors'])) {
      $chart_definition['colors'] = $element['#colors'];
    }

    // Dark mode.
    if (!empty($element['#library_type_options']['enable_dark_mode'])
      || ($chart_definition['theme']['mode'] ?? '') === 'dark'
      || ($element['#raw_options']['theme']['mode'] ?? '') === 'dark'
    ) {
      $chart_definition['theme']['mode'] = 'dark';
      $chart_definition['title']['style']['color'] = '#ffffff';
      $chart_definition['subtitle']['style']['color'] = '#ffffff';
      unset($chart_definition['chart']['background']);
    }

    // Gauge specific configuration.
    if ($element['#chart_type'] === 'gauge') {
      // Fill gradient based on gauge settings.
      $chart_definition['fill']['gradient']['opacityFrom'] = (int) $element['#gauge']['min'];
      $chart_definition['fill']['gradient']['opacityTo'] = (int) $element['#gauge']['max'];
      $chart_definition['fill']['gradient']['type'] = 'horizontal';
      $stops = [];

      // If the following have a value, place them in the stops array.
      if ((int) $element['#gauge']['min'] === 0 || !empty($element['#gauge']['min'])) {
        $stops[] = (int) $element['#gauge']['min'];
      }
      if (!empty($element['#gauge']['yellow_from'])) {
        $stops[] = (int) $element['#gauge']['yellow_from'];
      }
      if (!empty($element['#gauge']['green_from'])) {
        $stops[] = (int) $element['#gauge']['green_from'];
      }
      if (!empty($element['#gauge']['max'])) {
        $stops[] = (int) $element['#gauge']['max'];
      }
      $chart_definition['fill']['gradient']['stops'] = $stops;
    }

    // Merge in chart raw options.
    if (!empty($element['#raw_options'])) {
      $chart_definition = NestedArray::mergeDeepArray([
        $chart_definition,
        $element['#raw_options'],
      ]);
    }

    return $chart_definition;
  }

  /**
   * Utility to populate data.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  protected function populateData(array &$element, array $chart_definition) {
    $categories = [];
    $chart_type = $this->chartTypeConversion($element['#chart_type']);
    // Loop through the Chart render elements looking for the xaxis, which
    // contains the labels.
    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_xaxis' && !empty($element[$key]['#labels'])) {
        if (in_array($chart_type, ['pie', 'donut'])) {
          $categories = $element[$key]['#labels'];
          break;
        }
        $categories[] = $element[$key]['#labels'];
      }
    }
    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_data') {
        $series = [];
        $series_data = [];

        // Make sure defaults are loaded.
        if (empty($element[$key]['#defaults_loaded'])) {
          $element[$key] += $this->elementInfo->getInfo($element[$key]['#type']);
        }

        // Convert target named axis keys to integers.
        if (isset($element[$key]['#target_axis'])) {
          $axis_name = $element[$key]['#target_axis'];
          $axis_index = 0;
          foreach (Element::children($element) as $axis_key) {
            if ($element[$axis_key]['#type'] === 'chart_yaxis') {
              if ($axis_key === $axis_name) {
                break;
              }
              $axis_index++;
            }
          }
          $series['yaxis'] = $axis_index;
        }

        // Allow data to provide the labels.
        // This will override the axis settings.
        if ($element[$key]['#labels'] && !in_array($element[$key]['#chart_type'], [
          'scatter',
          'bubble',
        ])) {
          foreach ($element[$key]['#labels'] as $label_index => $label) {
            $series_data[$label_index][0] = $label;
          }
        }
        elseif (!empty($categories) && in_array($chart_type, ['pie', 'donut'])) {
          foreach ($categories as $label_index => $label) {
            $series_data[$label_index][0] = $label;
          }
        }

        // Populate the data.
        foreach ($element[$key]['#data'] as $data_index => $data) {
          // Check if the format of the data is an array.
          $data_is_array = is_array($data);
          // Specific chart types require a format like:
          // ['x' => 'label', 'y' => [10, 15]].
          if ($data_is_array && !empty($categories) && in_array($chart_type, ['rangeArea', 'boxPlot', 'candlestick'])) {
            $series_data[$data_index] = [
              'x' => $categories[0][$data_index],
              'y' => $data,
            ];
          }
          else {
            // Generally pie or donut charts, but not when grouping colors are
            // used.
            if (isset($series_data[$data_index])) {
              $series_data[$data_index][] = $data;
            }
            elseif (in_array($chart_type, ['bubble', 'donut', 'gauge', 'pie', 'radialBar', 'scatter'])) {
              $series_data[$data_index] = $data;
              $name = $series_data[$data_index]['name'] ?? NULL;
              if (!empty($name)) {
                $chart_definition['labels'][] = $name;
              }
              if (!empty($element[$key]['#grouping_colors'][$data_index][$name])) {
                $series_data[$data_index]['color'] = $element[$key]['#grouping_colors'][$data_index][$name];
              }
              elseif (!empty($element[$key]['#grouping_colors'][$data_index]) && is_array($element[$key]['#grouping_colors'][$data_index])) {
                $chart_definition['colors'][$data_index] = reset($element[$key]['#grouping_colors'][$data_index]);
              }
            }
            else {
              // Check if categories exist and have the required index.
              if (!empty($categories) && isset($categories[0][$data_index])) {
                $series_data[$data_index] = [
                  'x' => $categories[0][$data_index],
                  'y' => $data,
                ];
              }
              else {
                // Fallback when categories are not available.
                $series_data[$data_index] = [
                  'x' => $data_index,
                  'y' => $data,
                ];
              }
            }
          }
        }
        // In the case of combo charts, we need to pass the chart type at the
        // series level.
        $series['type'] = !empty($element[$key]['#chart_type']) ? $element[$key]['#chart_type'] : $element['#chart_type'];
        $series['type'] = $this->chartTypeConversion($series['type']);
        if ($element['#chart_type'] === 'donut') {
          // Add innerSize to differentiate between donut and pie.
          $series['innerSize'] = '40%';
        }
        $series['name'] = $element[$key]['#title'];
        if (empty($element['#library_type_options']['enable_dumbbell'])) {
          $series['color'] = $element[$key]['#color'];
        }
        else {
          $series['type'] = 'rangeBar';
        }
        if (!empty($series['type']) && in_array($series['type'], ['boxPlot', 'candlestick', 'line'])) {
          $chart_definition['stroke']['width'][] = 2;
        }
        else {
          $chart_definition['stroke']['width'][] = 0;
        }
        if ($series['type'] === 'line' && !empty($element['#polar'])) {
          unset($series['type']);
          unset($chart_definition['stroke']);
        }

        // Remove unnecessary keys to trim down the resulting JS settings.
        ChartElement::trimArray($series);

        // Assign the data to the series.
        $series['data'] = $series_data;

        // Merge in series raw options.
        if (!empty($element[$key]['#raw_options'])) {
          $series = NestedArray::mergeDeepArray([
            $series,
            $element[$key]['#raw_options'],
          ]);
        }

        // Handle the 'y_only' chart types defined in charts.charts_types.yml.
        if (!empty($series['type']) && in_array($series['type'], ['pie', 'donut', 'gauge', 'radialBar'])) {
          // Check if $series['data'] is in the format [1, 2, 3].
          if (is_array($series['data'][0])) {
            $series_data = array_column($series['data'], 1);
          }
          else {
            // Remove all the unnecessary keys.
            $series_data = $series['data'];
          }
          // If the type is gauge or radialBar, we need to set the fill colors.
          if (in_array($series['type'], ['gauge', 'radialBar'])) {
            if ($element[$key]['#color']) {
              $chart_definition['fill']['colors'] = $element[$key]['#color'];
            }
            // Apexcharts expects a percentage for the gauge. If $series_data[0]
            // is greater than 100, we need to divide it by the max value.
            // The Charts style plugin is not configured to deliver more than
            // one series (it might be good to allow this in the future);
            // regardless, we need to accommodate charts built from the field
            // or the Charts API.
            foreach ($series_data as $series_key => $series_value) {
              if ($series_value > 100) {
                if (!empty($element['#gauge']['max'])) {
                  // @todo Allow users to configure the precision.
                  $percent = round($series_value / (int) $element['#gauge']['max'], 2) * 100;
                  $series_data[$series_key] = $percent;
                }
              }
            }
          }
          // Pass the series data into the chart definition.
          $chart_definition['series'] = $series_data;
          // If the series data is a one-dimensional array, we need to set the
          // labels to the series name. A good example would be for a radialBar.
          if (empty(array_column($series['data'], 0))) {
            if (!empty($series['name'])) {
              $chart_definition['labels'][] = $series['name'];
            }
          }
          else {
            // If the series data is a two-dimensional array, we set the labels
            // with the first column of the series data.
            $chart_definition['labels'] = array_column($series['data'], 0);
          }
        }
        else {
          $chart_definition['series'][] = $series;
        }

        // Merge in any point-specific data points.
        foreach (Element::children($element[$key]) as $sub_key) {
          if ($element[$key][$sub_key]['#type'] === 'chart_data_item') {
            // Make sure defaults are loaded.
            if (empty($element[$key][$sub_key]['#defaults_loaded'])) {
              $element[$key][$sub_key] += $this->elementInfo->getInfo($element[$key][$sub_key]['#type']);
            }

            $data_item = $element[$key][$sub_key];
            $series_point = &$chart_definition['series'][$key]['data'][$sub_key];

            // Convert the point from a simple data value to a complex point.
            if (!isset($series_point['data'])) {
              $data = $series_point;
              $series_point = [];
              if (is_array($data)) {
                $series_point['name'] = $data[0];
                $series_point['y'] = $data[1];
              }
              else {
                $series_point['y'] = $data;
              }
            }
            if (isset($data_item['#data'])) {
              if (is_array($data_item['#data'])) {
                $series_point['x'] = $data_item['#data'][0];
                $series_point['y'] = $data_item['#data'][1];
              }
              else {
                $series_point['y'] = $data_item['#data'];
              }
            }
            if ($data_item['#title']) {
              $series_point['name'] = $data_item['#title'];
            }

            // Setting the color requires several properties for consistency.
            $series_point['color'] = $data_item['#color'];
            $series_point['fillColor'] = $data_item['#color'];
            $series_point['states']['hover']['fillColor'] = $data_item['#color'];
            $series_point['states']['select']['fillColor'] = $data_item['#color'];
            ChartElement::trimArray($series_point);

            // Merge in point raw options.
            if (!empty($data_item['#raw_options'])) {
              $series_point = NestedArray::mergeDeepArray([
                $series_point,
                $data_item['#raw_options'],
              ]);
            }
          }
        }

      }
    }

    return $chart_definition;
  }

  /**
   * Populate axes.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  protected function populateAxes(array $element, array $chart_definition) {
    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_xaxis' || $element[$key]['#type'] === 'chart_yaxis') {
        // Make sure defaults are loaded.
        if (empty($element[$key]['#defaults_loaded'])) {
          $element[$key] += $this->elementInfo->getInfo($element[$key]['#type']);
        }

        // Populate the chart data.
        $axis_type = $element[$key]['#type'] === 'chart_xaxis' ? 'xaxis' : 'yaxis';
        $axis = [];
        $axis['title']['text'] = $element[$key]['#title'];
        if (!empty($element[$key]['#labels']) && !in_array($element['#chart_type'], ['pie', 'donut'])) {
          $axis['categories'] = $element[$key]['#labels'];
        }
        $axis['opposite'] = $element[$key]['#opposite'];

        // Merge in axis raw options.
        if (!empty($element[$key]['#raw_options'])) {
          $axis = NestedArray::mergeDeepArray([
            $axis,
            $element[$key]['#raw_options'],
          ]);
        }

        if ($axis_type === 'yaxis') {
          $chart_definition[$axis_type][] = $axis;
        }
        else {
          $chart_definition[$axis_type] = $axis;
        }
      }
    }

    return $chart_definition;
  }

  /**
   * Convert module chart type conventions to ApexCharts conventions.
   *
   * @param string|null $chart_type
   *   The chart type.
   *
   * @return string
   *   The converted chart type.
   */
  protected function chartTypeConversion(?string $chart_type): string {
    if (empty($chart_type)) {
      return '';
    }

    $chart_type_map = [
      'arearange' => 'rangeArea',
      'boxplot' => 'boxPlot',
      'column' => 'bar',
      'gauge' => 'radialBar',
      'rangebar' => 'rangeBar',
      'spline' => 'line',
    ];

    return !empty($chart_type_map[$chart_type]) ? $chart_type_map[$chart_type] : $chart_type;
  }

}
