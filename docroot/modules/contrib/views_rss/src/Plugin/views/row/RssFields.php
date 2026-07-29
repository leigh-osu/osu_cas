<?php

namespace Drupal\views_rss\Plugin\views\row;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders an RSS item based on fields.
 *
 * @ViewsRow(
 *   id = "views_rss_fields",
 *   title = @Translation("Advanced RSS feed"),
 *   help = @Translation("Display fields as RSS items."),
 *   theme = "views_view_row_rss",
 *   display_types = {"feed"}
 * )
 */
class RssFields extends RowPluginBase {

  /**
   * Does the row plugin support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * The module handler service.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a RssPluginBase  object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
    );
  }

  /**
   * Function defineOptions.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $item_elements = views_rss_get('item_elements');
    if (count($item_elements)) {
      foreach ($item_elements as $module => $module_item_elements) {
        foreach (array_keys($module_item_elements) as $element) {
          [$namespace, $element_name] = views_rss_extract_element_names($element, 'core');
          $options['item']['contains'][$namespace]['contains'][$module]['contains'][$element_name] = ['default' => NULL];
        }
      }
    }

    return $options;
  }

  /**
   * Function buildOptionsForm.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $initial_labels = ['' => $this->t('- None -')];
    $view_fields_labels = $this->displayHandler->getFieldLabels();
    $view_fields_labels = array_merge($initial_labels, $view_fields_labels);

    // Element groups could be used both in channel and item settings.
    $element_groups = views_rss_get('element_groups');

    $item_elements = views_rss_get('item_elements');
    if (count($item_elements)) {
      foreach ($item_elements as $module => $module_item_elements) {
        foreach ($module_item_elements as $element => $definition) {
          if (!isset($definition['configurable']) || $definition['configurable']) {
            [$namespace, $element_name] = views_rss_extract_element_names($element, 'core');
            // Add fieldset for namespace if not yet added.
            if (!isset($form['item'][$namespace])) {
              $form['item'][$namespace] = [
                '#type' => 'details',
                '#title' => $this->t('Item elements : @namespace', ['@namespace' => $namespace]),
                '#description' => $this->t('Select fields containing relevant values for &lt;item&gt; elements in "@namespace" namespace. See <a href="@guide_url">Views RSS documentation</a> for more information.', [
                  '@namespace' => $namespace,
                  '@guide_url' => Url::fromUri('http://drupal.org/node/1344136')->toString(),
                ]),
                '#open' => FALSE,
              ];
            }
            // Prepare form element.
            $default_value = NULL;
            if (!empty($this->options['item'][$namespace][$module][$element_name])) {
              $default_value = $this->options['item'][$namespace][$module][$element_name];
            }
            elseif (!empty($definition['group'])) {
              $default_value = $this->options['item'][$namespace][$module][$definition['group']][$element_name];
            }
            $form_item = [
              '#type' => 'select',
              '#title' => Xss::filter($definition['title'] ?? $element_name),
              '#description' => !empty($definition['description']) ? Xss::filter($definition['description']) : NULL,
              '#options' => $view_fields_labels,
              '#default_value' => $default_value,
            ];
            // Allow to overwrite default form element.
            if (!empty($definition['settings form'])) {
              $form_item = array_merge($form_item, $definition['settings form']);
              // Make sure that #options is an associative array.
              if (!empty($definition['settings form']['#options'])) {
                $form_item['#options'] = views_rss_map_assoc($definition['settings form']['#options']);
              }
            }
            // Add help link if provided.
            if (isset($definition['help']) && $definition['help']) {
              $form_item['#description'] .= ' ' . Link::fromTextAndUrl('[?]', Url::fromUri($definition['help']))->toString();
            }

            // Check if element should be displayed in a subgroup.
            if (isset($definition['group']) && $definition['group']) {
              // Add a subgroup to the form if it not yet added.
              if (!isset($form['item'][$namespace][$module][$definition['group']])) {
                // Does module provide the group definition?
                $group_title = !empty($element_groups[$module][$definition['group']]['title']) ? $element_groups[$module][$definition['group']]['title'] : $definition['group'];
                $group_description = !empty($element_groups[$module][$definition['group']]['description']) ? $element_groups[$module][$definition['group']]['description'] : NULL;
                $form['item'][$namespace][$module][$definition['group']] = [
                  '#type' => 'details',
                  '#title' => Xss::filter($group_title),
                  '#description' => Xss::filter($group_description),
                  '#open' => FALSE,
                ];
              }
              $form['item'][$namespace][$module][$definition['group']][$element_name] = $form_item;
            }
            // Display element normally (not within a subgroup).
            else {
              $form['item'][$namespace][$module][$element_name] = $form_item;
            }
          }
        }
      }
    }
  }

  /**
   * Validates whether views_rss_core module exists or not.
   */
  public function validate() {
    $errors = parent::validate();

    if (!$this->moduleHandler->moduleExists('views_rss_core')) {
      $errors[] = $this->t('You have to enable <em>Views RSS: Core Elements</em> module to have access to basic feed elements.');
    }
    else {
      // An item MUST contain either a title or description.
      // All other elements are optional according to RSS specification.
      if (empty($this->options['item']['core']['views_rss_core']['title']) && empty($this->options['item']['core']['views_rss_core']['description'])) {
        $errors[] = $this->t('You have to configure either <em>title</em> or <em>description</em> core element.');
      }
    }

    return $errors;
  }

  /**
   * Protected function mapRow.
   */
  protected function mapRow($row) {
    $rendered_fields = $raw_fields = [];
    $field_ids = array_keys($this->view->field);
    if (!empty($field_ids)) {
      foreach ($field_ids as $field_id) {
        // Render the final field value.
        $rendered_fields[$field_id] = $this->getField($row->index, $field_id);
        // Also let's keep raw value for further processing.
        $raw_fields[$field_id] = [];
        if (method_exists($this->view->field[$field_id], 'getItems')) {
          $raw_fields[$field_id]['items'] = $this->view->field[$field_id]->getItems($row);
        }
      }
    }

    // Rewrite view rows to XML item rows.
    $item_elements = views_rss_get('item_elements');
    $raw_item = [];
    $item = [];
    foreach ($rendered_fields as $field_id => $rendered_field) {
      $item = $raw_item = [];

      foreach ($item_elements as $module => $module_item_elements) {
        foreach ($module_item_elements as $element => $definition) {
          [$namespace, $element_name] = views_rss_extract_element_names($element, 'core');

          if (!empty($this->options['item'][$namespace][$module][$element_name])) {
            $field_name = $this->options['item'][$namespace][$module][$element_name];
          }
          elseif (!empty($definition['group']) && !empty($this->options['item'][$namespace][$module][$definition['group']][$element_name])) {
            $field_name = $this->options['item'][$namespace][$module][$definition['group']][$element_name];
          }
          else {
            $field_name = NULL;
          }

          // Assign values for all elements, not only those defined in view
          // settings. If element value is not defined in view settings, let's
          // just assign NULL. It will not be passed to final theme function
          // anyway during processing taking place in
          // template_preprocess_views_view_views_rss().
          if (!empty($rendered_fields[$field_name])) {
            $item[$module][$element] = $rendered_fields[$field_name];
          }
          else {
            $item[$module][$element] = NULL;
          }

          // Keep raw values too.
          if (!empty($raw_fields[$field_name])) {
            $raw_item[$module][$element] = $raw_fields[$field_name];
          }
        }
      }
    }

    $this->view->views_rss['raw_items'][$row->index] = $raw_item;
    return $item;
  }

  /**
   * Function render.
   */
  public function render($row) {
    static $row_index;
    if (!isset($row_index)) {
      $row_index = 0;
    }

    $item_elements = views_rss_get('item_elements');
    $item_data = $this->mapRow($row);

    // Preprocess whole item array before preprocessing separate elements.
    $hook = 'views_rss_preprocess_item';
    $modules = [];
    $this->moduleHandler->invokeAllWith($hook, function (callable $hook, string $module) use (&$modules) {
      $modules[] = $module;
    });
    $item_variables = [
      'item' => &$item_data,
      'view' => $this->view,
    ];
    // Add raw row if generated based on raw item values provided by field
    // formatter.
    if (!empty($this->view->views_rss['raw_items'][$row->index])) {
      $item_variables['raw'] = $this->view->views_rss['raw_items'][$row->index];
    }
    foreach ($modules as $module) {
      $this->moduleHandler->invoke($module, $hook, [$item_variables]);
    }

    $item = new \stdClass();
    $item->elements = [];

    // Process each element separately.
    foreach ($item_data as $module => $module_item_elements) {
      foreach ($module_item_elements as $element => $value) {
        // Avoid double encoding: the $value might be already encoded here,
        // depending on the field configuration/processing, and because we know
        // it will be encoded again when the whole feed array will be passed to
        // Drupal render, let's make sure we decode it here first.
        if (is_string($value)) {
          $value = htmlspecialchars_decode($value, ENT_QUOTES);
        }

        // Start building XML element array compatible with Drupal render.
        // @todo review this to ensure that no warnings are generated.
        $rss_elements = [
          [
            'key' => $element,
            'value' => $value,
            'cdata' => !empty($item_elements[$module][$element]['cdata']),
          ],
        ];

        // Preprocess element initial value if required.
        if (isset($item_elements[$module][$element]['preprocess functions']) && is_array($item_elements[$module][$element]['preprocess functions'])) {
          foreach ($item_elements[$module][$element]['preprocess functions'] as $preprocess_function) {
            if (function_exists($preprocess_function)) {
              $item_variables = [
                'elements' => &$rss_elements,
                'item' => $item_data,
                'view' => $this->view,
              ];
              // Add raw item if provided by field formatter.
              if (!empty($this->view->views_rss['raw_items'][$row->index][$module][$element])) {
                $item_variables['raw'] = $this->view->views_rss['raw_items'][$row->index][$module][$element];
              }
              $preprocess_function($item_variables);
            }
          }
        }
        // If there is no value and no attributes (in case of self-closing
        // elements) already set for the element at this stage, it is not going
        // to be set at any point further, so the element should not be added
        // to the feed.
        foreach ($rss_elements as $key => $rss_element) {
          if (empty($rss_element['value']) && empty($rss_element['attributes'])) {
            unset($rss_elements[$key]);
          }
        }
        if (empty($rss_elements)) {
          continue;
        }

        // Special processing for title, description and link elements, as these
        // are hardcoded both in template_preprocess_views_view_row_rss() and in
        // views-view-row-rss.html.twig, and we try to keep the compatibility.
        if ($element === 'title' || $element === 'link') {
          $rss_element = reset($rss_elements);
          $item->$element = $rss_element['value'];
        }

        // template_preprocess_views_view_row_rss() expects the description to
        // be a renderable array.
        elseif ($element === 'description') {
          $rss_element = reset($rss_elements);
          if (is_string($rss_element['value'])) {
            $item->$element = ['#markup' => $rss_element['value']];
          }
          else {
            $item->$element = $rss_element['value'];
          }
        }

        // All other elements are custom and should go into $item->elements.
        else {
          $item->elements = array_merge($item->elements, $rss_elements);
        }
      }
    }

    // Merge RDF namespaces in the XML namespaces in case they are used
    // further in the RSS content.
    if (function_exists('rdf_get_namespaces') && !empty($this->view->style_plugin->options['namespaces']['add_rdf_namespaces'])) {
      $xml_rdf_namespaces = [];
      foreach (rdf_get_namespaces() as $prefix => $uri) {
        $xml_rdf_namespaces['xmlns:' . $prefix] = $uri;
      }
      $this->view->style_plugin->namespaces += $xml_rdf_namespaces;
    }

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#row' => $item,
      '#field_alias' => $this->field_alias ?? '',
    ];

    return $build;
  }

  /**
   * Retrieves a views field value from the style plugin.
   *
   * @param int $index
   *   The index count of the row as expected by views_plugin_style::getField().
   * @param string $field_id
   *   The ID assigned to the required field in the display.
   */
  public function getField($index, $field_id) {
    if (empty($this->view->style_plugin) || !is_object($this->view->style_plugin) || empty($field_id)) {
      return '';
    }
    return $this->view->style_plugin->getField($index, $field_id);
  }

}
