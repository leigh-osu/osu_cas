<?php

namespace Drupal\charts_highcharts_maps\Plugin\views\style;

use Drupal\charts\ChartManager;
use Drupal\charts\Plugin\views\style\ChartsPluginStyleChart;
use Drupal\charts\TypeManager;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\core\form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Style plugin to render view as a chart.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "chart_highmap",
 *   title = @Translation("HighMaps"),
 *   help = @Translation("Render a map of your data."),
 *   theme = "views_view_charts_highcharts_maps",
 *   display_types = { "normal" }
 * )
 */
class HighMapsPluginStyleChart extends ChartsPluginStyleChart {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a HighMapsPluginStyleChart object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\charts\ChartManager $chartsManager
   *   The chart manager service.
   * @param \Drupal\charts\TypeManager $chart_type_manager
   *   The chart type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, ChartManager $chartsManager, TypeManager $chart_type_manager, RouteMatchInterface $route_match, EntityFieldManagerInterface $entityFieldManager, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory, $chartsManager, $chart_type_manager, $route_match);
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('plugin.manager.charts'),
      $container->get('plugin.manager.charts_type'),
      $container->get('current_route_match'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['topology']['default'] = '';
    $options['map_data_source_settings']['default'] = [
      'json_file' => '',
      'vid' => '',
      'tid' => 0,
      'json_field_name' => '',
    ];
    $options['series_join_by_property_name']['default'] = 'hasc';
    $options['series_keys_mapping_options']['default'] = ['hasc', 'value'];
    $options['popup']['default'] = 0;
    $options['popup_content']['default'] = NULL;

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['chart_settings']['#after_build'][] = [static::class, 'chartsSettingsAfterBuild'];

    $map_data_source_settings_wrapper_id = Html::cleanCssIdentifier($this->view->id() . '--' . $this->view->current_display . '--map-data-source-settings');
    $form['map_data_source_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Map data settings'),
      '#description' => $this->t('Use either option below (JSON file or field on a taxonomy term) to provide the geographic data for your map; choosing one option will hide the other.'),
      '#collapsible' => TRUE,
      '#attributes' => [
        'id' => $map_data_source_settings_wrapper_id,
      ],
      '#open' => TRUE,
      '#required' => TRUE,
      '#tree' => TRUE,
    ];

    $form['map_data_source_settings']['json_file'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('JSON File'),
      '#default_value' => $this->getUriAsDisplayableString($this->options['map_data_source_settings']['json_file'] ?? ''),
      '#description' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Start typing the name of a JSON file to select it. You can also enter an internal path such as %path-to-file or an external URL such as %url.', [
            '%path-to-file' => '/files/data/example.json',
            '%url' => 'https://example.com/files/data/example.json',
          ]),
          $this->t('Note: Drupal token can be used as well.'),
        ],
      ],
      '#maxlength' => 2048,
      '#target_type' => 'file',
      '#selection_handler' => 'default:charts_highcharts_charts_json_file_entity_selection',
      '#selection_settings' => [
        'map_data_source_settings' => 'json_file',
      ],
      // Disable autocompletion when the first character is '/'.
      '#attributes' => ['data-autocomplete-first-character-blacklist' => '/'],
      // We are doing our own processing in static::getUriAsDisplayableString().
      '#process_default_value' => FALSE,
      '#element_validate' => [[static::class, 'validateJsonFileUriElement']],
      '#states' => [
        'visible' => [
          ':input[name="style_options[map_data_source_settings][vid]"]' => ['value' => ''],
        ],
      ],
    ];

    $vid = $this->extractMapDataSourceVid($form_state);
    $form['map_data_source_settings']['vid'] = [
      '#type' => 'select',
      '#title' => $this->t('Taxonomy vocabulary'),
      '#description' => $this->t('The vocabulary of the taxonomy term that have the json field for data mapping'),
      '#options' => ['' => $this->t('- Select -')] + $this->vocabularyOptions(),
      '#default_value' => $vid,
      '#ajax' => [
        'callback' => [get_called_class(), 'refreshTermJsonFieldSelection'],
        'wrapper' => $map_data_source_settings_wrapper_id,
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Refreshing...'),
        ],
      ],
      '#limit_validation_errors' => [],
      '#states' => [
        'visible' => [
          ':input[name="style_options[map_data_source_settings][json_file]"]' => ['value' => ''],
        ],
      ],
    ];

    if ($vid) {
      $tid = $this->options['map_data_source_settings']['tid'] ?? 0;
      $vid_states = [
        'invisible' => [
          ':input[name="style_options[map_data_source_settings][vid]"]' => ['value' => ''],
        ],
        'required' => [
          ':input[name="style_options[map_data_source_settings][vid]"]' => ['!value' => ''],
        ],
      ];
      $form['map_data_source_settings']['tid'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Taxonomy term'),
        '#description' => $this->t('The taxonomy term storing the json data.'),
        '#target_type' => 'taxonomy_term',
        '#default_value' => $tid ? Term::load($tid) : NULL,
        '#required' => TRUE,
        '#selection_handler' => 'default',
        '#selection_settings' => [
          'target_bundles' => [$vid],
        ],
        '#states' => $vid_states,
      ];
      $form['map_data_source_settings']['json_field_name'] = [
        '#type' => 'select',
        '#title' => $this->t('JSON field name'),
        '#options' => ['' => $this->t('- Select -')] + $this->jsonFieldOptions($vid),
        '#default_value' => $this->options['map_data_source_settings']['json_field_name'] ?? '',
        '#required' => TRUE,
        '#states' => $vid_states,
      ];
    }

    $description = $this->t('What property to join the "mapData" to the value data. For example, if joinBy is "code", the mapData items with a specific code is merged into the data with the same code. For maps loaded from GeoJSON, the keys may be held in each point\'s properties object. The joinBy option can also be an array of two values, where the first points to a key in the mapData, and the second points to another key in the data. See <a href="@link">documentation</a> for more information.<br><b>If you have two values separate them with a comma.</b>',
      [
        '@link' => 'https://api.highcharts.com/highmaps/series.map.joinBy',
      ]);
    $form['series_join_by_property_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Series JoinBy'),
      '#description' => $description,
      '#default_value' => $this->options['series_join_by_property_name'],
    ];

    $form['series_keys_mapping_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Series keys'),
      '#description' => $this->t('An array specifying which option maps to which key in the data point array. This makes it convenient to work with unstructured data arrays from different sources. See <a href="@link">documentation</a> for more information.', [
        '@link' => 'https://api.highcharts.com/highmaps/series.map.keys',
      ]),
      '#collapsible' => TRUE,
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['series_keys_mapping_options'][0] = [
      '#type' => 'textfield',
      '#title' => $this->t('First key'),
      '#default_value' => $this->options['series_keys_mapping_options'][0],
    ];
    $form['series_keys_mapping_options'][1] = [
      '#type' => 'textfield',
      '#title' => $this->t('Second key'),
      '#default_value' => $this->options['series_keys_mapping_options'][1],
    ];
    $form['popup'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable map pop-ups'),
      '#default_value' => $this->options['popup'],
    ];
    // Select a field form the View to be used as the pop-up content.
    $field_options = $this->displayHandler->getFieldLabels();
    $form['popup_content'] = [
      '#type' => 'radios',
      '#title' => $this->t('Pop-up content'),
      '#options' => $field_options,
      '#default_value' => $this->options['popup_content'],
      '#description' => $this->t('Select the field to provide content for the map pop-up.'),
    ];

    $form_state->set('default_options', $this->options);
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $map_data_source_settings = $form_state->getValue([
      'style_options',
      'map_data_source_settings',
    ]);
    if (empty($map_data_source_settings['json_file']) && empty($map_data_source_settings['vid'])) {
      $form_state->setError($form['map_data_source_settings'], $this->t('You must select a JSON file or a taxonomy term vocabulary for the map data source.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $chart = parent::render();

    if (empty($chart['#legend_title']) && !empty($this->options['chart_settings']['yaxis']['title'])) {
      $chart['#legend_title'] = $this->options['chart_settings']['yaxis']['title'];
    }
    $chart['#map_data_source_settings'] = $this->options['map_data_source_settings'];
    $chart['#series_data_settings'] = [
      'join_by_property_name' => $this->options['series_join_by_property_name'],
      'keys_mapping_options' => $this->options['series_keys_mapping_options'],
      'tooltip' => [
        'value_prefix' => $this->options['chart_settings']['yaxis']['prefix'] ?? '',
        'value_suffix' => $this->options['chart_settings']['yaxis']['suffix'] ?? '',
        'value_decimals' => $this->options['chart_settings']['yaxis']['decimal_count'] ?? NULL,
      ],
    ];

    // Add the popup content and/or grouping data to the chart.
    if (!empty($this->options['popup']) || isset($this->options['grouping'][0]['field'])) {
      $rendered_fields = $this->rendered_fields;
      $grouping_field_values = [];
      $popup_selected = !empty($this->options['popup']);
      if ($popup_selected) {
        $popup_field = $this->options['popup_content'];
        $annotations = [];
      }
      // Loop through the Chart elements that are of the type 'chart_data'.
      foreach (Element::children($chart) as $key) {
        if (empty($chart[$key]['#grouping_colors'])) {
          $empty_grouping_colors = TRUE;
        }
        else {
          $empty_grouping_colors = FALSE;
          $chart[$key]['#original_grouping_colors'] = TRUE;
        }
        if ($chart[$key]['#type'] === 'chart_data') {
          foreach ($chart[$key]['#data'] as $index => &$data) {
            if ($popup_selected) {
              $data[] = 'annotation_id_' . $index;
              $annotations[] = [
                'labels' => [
                  [
                    'point' => 'annotation_id_' . $index,
                    'text' => $rendered_fields[$index][$popup_field],
                  ],
                ],
                'visible' => FALSE,
              ];
            }
            if (isset($this->options['grouping'][0]['field'])) {
              $data['colorKey'] = (string) $rendered_fields[$index][$this->options['grouping'][0]['field']];
              if ($empty_grouping_colors) {
                if (!in_array($data['colorKey'], $grouping_field_values)) {
                  $grouping_field_values[] = $data['colorKey'];
                }
                $grouping_field_values_lookup = array_flip($grouping_field_values);
                $color_index = $grouping_field_values_lookup[$data['colorKey']] ?? 0;
                $chart[$key]['#grouping_colors'][] = [$data['colorKey'] => $chart['#colors'][$color_index]];
              }
            }
          }
        }
      }
      if ($popup_selected) {
        $chart['#raw_options']['annotations'] = $annotations;
        $chart['#attributes']['data-use-annotations'] = 'true';
      }
    }

    return $chart;
  }

  /**
   * Ajax callback to refresh the term and JSON field selection.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The map data source settings.
   */
  public static function refreshTermJsonFieldSelection(array $form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = array_slice($triggering_element['#array_parents'], 0, -1);
    return NestedArray::getValue($form, $array_parents);
  }

  /**
   * Alter the chart settings form after build.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\core\form\FormStateInterface $form_state
   *   The form_state.
   *
   * @return array
   *   The altered form element.
   */
  public static function chartsSettingsAfterBuild(array $element, FormStateInterface $form_state) {
    // Hiding the display settings not available for Highcarts Maps.
    $element['display']['data_markers']['#access'] = FALSE;
    $element['display']['connect_nulls']['#access'] = FALSE;
    $element['display']['three_dimensional']['#access'] = FALSE;
    $element['display']['polar']['#access'] = FALSE;

    // Hiding the xAxis settings because it's not needed for the highmap.
    $element['xaxis']['#access'] = FALSE;

    // Renaming the yAxis title to set it to map display settings.
    $element['yaxis']['#title'] = new TranslatableMarkup('Highcharts Map display settings');

    $element['yaxis']['title']['#title'] = new TranslatableMarkup('Legend title');

    $element['yaxis']['prefix']['#title'] = new TranslatableMarkup('Value prefix');
    $element['yaxis']['prefix']['#description'] = new TranslatableMarkup("A string to prepend to each series' y value.");
    $element['yaxis']['suffix']['#title'] = new TranslatableMarkup('Value suffix');
    $element['yaxis']['suffix']['#description'] = new TranslatableMarkup("A string to append to each series' y value.");

    $element['yaxis']['decimal_count']['#title'] = new TranslatableMarkup('Value decimals');
    $element['yaxis']['decimal_count']['#description'] = new TranslatableMarkup("How many decimals to show in each series' y value.");

    // Hiding unused elements.
    $element['yaxis']['min_max_label']['#access'] = FALSE;
    $element['yaxis']['min']['#access'] = FALSE;
    $element['yaxis']['max']['#access'] = FALSE;
    $element['yaxis']['labels_rotation']['#access'] = FALSE;

    return $element;
  }

  /**
   * Form element validation handler for the 'json_file' element.
   *
   * Disallows saving inaccessible or untrusted URLs.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The form.
   */
  public static function validateJsonFileUriElement(array $element, FormStateInterface $form_state, array $form): void {
    $uri = static::getUserEnteredStringAsUri($element['#value']);
    $form_state->setValueForElement($element, $uri);

    // If getUserEnteredStringAsUri() mapped the entered value to an 'internal:'
    // URI, ensure the raw value begins with '/'.
    if (parse_url($uri, PHP_URL_SCHEME) === 'internal' && $element['#value'][0] !== '/') {
      $form_state->setError($element, new TranslatableMarkup('Manually entered paths should start with "/".'));
    }
  }

  /**
   * Extract the map data source Vocabulary ID.
   *
   * @param \Drupal\core\form\FormStateInterface $form_state
   *   The form_state.
   *
   * @return mixed
   *   It will return mixed values.
   */
  protected function extractMapDataSourceVid(FormStateInterface $form_state) {
    $vid = $form_state->getValue([
      'style_options',
      'map_data_source_settings',
      'vid',
    ]);
    return $vid ? (string) $vid : ($this->options['map_data_source_settings']['vid'] ?? '');
  }

  /**
   * Get the vocabulary options.
   *
   * @return array
   *   It will return an array of options.
   */
  protected function vocabularyOptions(): array {
    /** @var \Drupal\taxonomy\VocabularyStorage $vocabulary_storage */
    $vocabulary_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $options = [];
    foreach ($vocabulary_storage->loadMultiple() as $vid => $vocabulary) {
      $options[$vid] = $vocabulary->label();
    }
    return $options;
  }

  /**
   * Get JSON field options.
   *
   * @param string $bundle
   *   The bundle.
   *
   * @return array
   *   It will return an array of options.
   */
  protected function jsonFieldOptions(string $bundle): array {
    $options = [];
    $entity_type_id = 'taxonomy_term';
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
    $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type_id);
    $excluded_fields = [
      $entity_type_definition->getKey('id'),
      $entity_type_definition->getKey('uuid'),
      $entity_type_definition->getKey('label'),
      $entity_type_definition->getKey('langcode'),
      $entity_type_definition->getKey('bundle'),
      $entity_type_definition->getKey('published'),
      'created',
      'changed',
      'default_langcode',
      'metatag',
      'parent',
      'path',
      'weight',
      'description',
    ];
    foreach ($fields as $field_name => $field) {
      if (in_array($field_name, $excluded_fields, TRUE) || str_starts_with($field_name, 'revision_') || str_starts_with($field_name, 'content_translation_')) {
        continue;
      }

      $options[$field_name] = $this->t('@label (Property: @name - Type: @type)', [
        '@label' => $field->getLabel(),
        '@name' => $field_name,
        '@type' => $field->getType(),
      ]);
    }
    return $options;
  }

  /**
   * Sets the entity field manager for this handler.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The new entity field manager.
   *
   * @return $this
   */
  protected function setEntityFieldManager(EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
    return $this;
  }

  /**
   * Sets the entity type manager for this handler.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @return $this
   */
  protected function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * The following two forms of URIs are transformed:
   * - 'entity:' URIs: to entity autocomplete ("label (entity id)") strings;
   * - 'internal:' URIs: the scheme is stripped.
   *
   * This method is the inverse of static::getUserEnteredStringAsUri().
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *   The displayable string.
   *
   * @see static::getUserEnteredStringAsUri()
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getUriAsDisplayableString(string $uri): string {
    $scheme = parse_url($uri, PHP_URL_SCHEME);
    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    if ($scheme === 'internal') {
      $displayable_string = explode(':', $uri, 2)[1];
    }
    elseif ($scheme === 'entity') {
      $entity = static::getFileEntityFromUri($uri);
      if ($entity instanceof FileInterface) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    return $displayable_string;
  }

  /**
   * Gets the file entity from URI.
   *
   * @param string $uri
   *   The file uri.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity if found, NULL otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getFileEntityFromUri(string $uri): ?FileInterface {
    [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
    if ($entity_type !== 'file') {
      return NULL;
    }
    return \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
  }

  /**
   * Gets the user-entered string as a URI.
   *
   * The following two forms of input are mapped to URIs:
   * - entity autocomplete ("label (entity id)") strings: to 'entity:' URIs;
   * - strings without a detectable scheme: to 'internal:' URIs.
   *
   * @param string $string
   *   The user-entered string.
   *
   * @return string
   *   The URI, if a non-empty $uri was passed.
   *
   * @see static::getUriAsDisplayableString()
   */
  protected static function getUserEnteredStringAsUri(string $string): string {
    $uri = trim($string);
    // Detect entity autocomplete string, map to 'entity:' URI.
    $file_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($string);
    if ($file_id !== NULL) {
      return 'entity:file/' . $file_id;
    }

    // Detect a scheme-less string, map to 'internal:' URI.
    if (!empty($string) && parse_url($string, PHP_URL_SCHEME) === NULL) {
      return 'internal:' . $string;
    }

    return $uri;
  }

}
