<?php

namespace Drupal\views_rss\Plugin\views\style;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default style plugin to render an RSS feed from fields.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "rss_fields",
 *   title = @Translation("Advanced RSS feed"),
 *   help = @Translation("Generates an RSS feed from fields in a view."),
 *   theme = "views_view_rss",
 *   display_types = {"feed"}
 * )
 */
class RssFields extends StylePluginBase {

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Does the style plugin support grouping of rows.
   *
   * @var bool
   */
  protected $usesGrouping = FALSE;

  /**
   * The namespaces to add to the output.
   *
   * @var array
   */
  public array $namespaces = [];

  /**
   * The channel elements present for this display.
   *
   * @see \Drupal\views\Plugin\views\style\Row
   */
  // phpcs:ignore
  public array $channel_elements = [];

  /**
   * The module handler service.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Configuration Factory.
   */
  protected ConfigFactoryInterface $configFactory;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
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
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $errors = parent::validate();

    $plugin = $this->displayHandler->getPlugin('row');
    if ($plugin->getPluginId() !== 'views_rss_fields') {
      $errors[] = $this->t('Style %style requires an <em>Advanced RSS feed</em> row plugin.', ['%style' => $this->definition['title']]);
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function attachTo(array &$build, $display_id, Url $feed_url, $title) {
    $url_options = [];
    $input = $this->view->getExposedInput();
    if ($input) {
      $url_options['query'] = $input;
    }
    $url_options['absolute'] = TRUE;

    $url = $feed_url->setOptions($url_options)->toString();

    // Add the RSS icon to the view.
    $this->view->feedIcons[] = [
      '#theme' => 'feed_icon',
      '#url' => $url,
      '#title' => $title,
    ];

    // Attach a link to the RSS feed, which is an alternate representation.
    $build['#attached']['html_head_link'][][] = [
      'rel' => 'alternate',
      'type' => 'application/rss+xml',
      'title' => $title,
      'href' => $url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Namespace defaults.
    $namespaces = views_rss_get('namespaces');
    if (count($namespaces)) {
      foreach ($namespaces as $module => $module_namespaces) {
        foreach (array_keys($module_namespaces) as $namespace) {
          $options['namespaces']['contains'][$module]['contains'][$namespace] = ['default' => NULL];
        }
      }
    }
    if (function_exists('rdf_get_namespaces')) {
      $options['namespaces']['contains']['add_rdf_namespaces'] = ['default' => FALSE];
    }

    // Channel element defaults.
    $channel_elements = views_rss_get('channel_elements');
    if (count($channel_elements)) {
      foreach ($channel_elements as $module => $module_channel_elements) {
        foreach (array_keys($module_channel_elements) as $element) {
          [$namespace, $element_name] = views_rss_extract_element_names($element, 'core');
          $options['channel']['contains'][$namespace]['contains'][$module]['contains'][$element_name] = ['default' => NULL];
        }
      }
    }

    // Other feed settings defaults.
    $options['feed_settings']['contains']['absolute_paths'] = ['default' => 1];
    $options['feed_settings']['contains']['feed_in_links'] = ['default' => 0];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $initial_labels = ['' => $this->t('- None -')];
    $view_fields_labels = $this->displayHandler->getFieldLabels();
    $view_fields_labels = array_merge($initial_labels, $view_fields_labels);

    $form['uses_fields']['#type'] = 'hidden';

    // Element groups could be used both in channel and item settings.
    $element_groups = views_rss_get('element_groups');

    // Channel elements settings.
    $channel_elements = views_rss_get('channel_elements');
    if (count($channel_elements)) {
      foreach ($channel_elements as $module => $module_channel_elements) {
        foreach ($module_channel_elements as $element => $definition) {
          if (!isset($definition['configurable']) || $definition['configurable']) {
            [$namespace, $element_name] = views_rss_extract_element_names($element, 'core');
            // Add fieldset for namespace if not yet added.
            if (!isset($form['channel'][$namespace])) {
              $form['channel'][$namespace] = [
                '#type' => 'details',
                '#title' => $this->t('Channel elements : @namespace', ['@namespace' => $namespace]),
                '#description' => $this->t('Provide values for &lt;channel&gt; elements in "@namespace" namespace. See <a href="@guide_url">Views RSS documentation</a> for more information.', [
                  '@namespace' => $namespace,
                  '@guide_url' => Url::fromUri('http://drupal.org/node/1344136')->toString(),
                ]),
                '#open' => FALSE,
              ];
            }
            // Prepare form element.
            $default_value = NULL;
            if (!empty($this->options['channel'][$namespace][$module][$element_name])) {
              $default_value = $this->options['channel'][$namespace][$module][$element_name];
            }
            $form_item = [
              '#type' => 'textfield',
              '#title' => Xss::filter($definition['title'] ?? $element_name),
              '#description' => !empty($definition['description']) ? Xss::filter($definition['description']) : NULL,
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
            if (!empty($definition['settings form options callback'])) {
              $function = $definition['settings form options callback'];
              $form_item['#options'] = views_rss_map_assoc($function());
            }
            // Add help link if provided.
            if (!empty($definition['help'])) {
              $form_item['#description'] .= ' ' . Link::fromTextAndUrl('[?]', Url::fromUri($definition['help']))->toString();
            }
            // Check if element should be displayed in a subgroup.
            if (!empty($definition['group'])) {
              // Add a subgroup to the form if it not yet added.
              if (!isset($form['channel'][$namespace][$module][$definition['group']])) {
                // Does module provide the group definition?
                $group_title = !empty($element_groups[$module][$definition['group']]['title']) ? $element_groups[$module][$definition['group']]['title'] : $definition['group'];
                $group_description = !empty($element_groups[$module][$definition['group']]['description']) ? $element_groups[$module][$definition['group']]['description'] : NULL;
                $form['channel'][$namespace][$module][$definition['group']] = [
                  '#type' => 'fieldset',
                  '#title' => Xss::filter($group_title),
                  '#description' => Xss::filter($group_description),
                  '#collapsible' => TRUE,
                  '#collapsed' => TRUE,
                ];
              }
              $form['channel'][$namespace][$module][$definition['group']][$element_name] = $form_item;
            }
            // Display element normally (not within a subgroup).
            else {
              $form['channel'][$namespace][$module][$element_name] = $form_item;
            }
          }
        }
      }
    }

    $form['namespaces'] = [
      '#type' => 'details',
      '#title' => $this->t('Namespaces'),
      '#open' => FALSE,
    ];

    if (function_exists('rdf_get_namespaces')) {
      $form['namespaces']['add_rdf_namespaces'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Merge RDF namespaces'),
        '#description' => $this->t('Enabling this option will merge RDF namespaces into the XML namespaces in case they are used in the RSS content.'),
        '#default_value' => $this->options['namespaces']['add_rdf_namespaces'],
      ];
    }

    // Undefined namespaces derived from <channel> and/or <item>
    // elements defined by extension modules.
    $namespaces = views_rss_get('namespaces');
    if (count($namespaces)) {
      foreach ($namespaces as $module => $module_namespaces) {
        foreach ($module_namespaces as $namespace => $definition) {
          if (empty($definition['uri'])) {
            // Add fieldset for namespace if not yet added.
            if (!isset($form['namespaces'])) {
              $form['namespaces']['#description'] = $this->t('Enter missing URLs for namespaces derived from &lt;channel&gt; and/or &lt;item&gt; elements defined by extension modules.');
            }
            if (!empty($this->options['namespaces'][$module][$namespace])) {
              $default_value = $this->options['namespaces'][$module][$namespace];
            }
            else {
              $default_value = NULL;
            }
            $form['namespaces'][$module][$namespace] = [
              '#type' => 'textfield',
              '#title' => $namespace,
              '#default_value' => $default_value,
            ];
          }
        }
      }
    }

    // Other feed settings.
    $form['feed_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Other feed settings'),
      '#open' => FALSE,
    ];
    $form['feed_settings']['absolute_paths'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Replace relative paths with absolute URLs"),
      '#description' => $this->t('Enabling this option will replace all relative paths (like <em>/node/1</em>) with absolute URLs (<em>@absolute_url</em>) in all feed elements configured to use this feature (for example &lt;description&gt; element).', [
        '@absolute_url' => trim($GLOBALS['base_url'], '/') . '/node/1',
      ]),
      '#default_value' => !empty($this->options['feed_settings']['absolute_paths']),
      '#weight' => 1,
    ];
    $form['feed_settings']['feed_in_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display feed icon in the links attached to the view'),
      '#default_value' => !empty($this->options['feed_settings']['feed_in_links']),
      '#weight' => 3,
    ];
  }

  /**
   * Function validateOptionsForm.
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $hook = 'views_rss_options_form_validate';
    $modules = [];
    $this->moduleHandler->invokeAllWith($hook, function (callable $hook, string $module) use (&$modules) {
      $modules[] = $module;
    });
    foreach ($modules as $module) {
      $this->moduleHandler->invoke($module, $hook, [$form, $form_state]);
    }
  }

  /**
   * Function submitOptionsForm.
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    $hook = 'views_rss_options_form_submit';
    $modules = [];
    $this->moduleHandler->invokeAllWith($hook, function (callable $hook, string $module) use (&$modules) {
      $modules[] = $module;
    });
    foreach ($modules as $module) {
      $this->moduleHandler->invoke($module, $hook, [$form, $form_state]);
    }
  }

  /**
   * Return an array of additional XHTML elements to add to the channel.
   *
   * @return array
   *   An array that can be passed to the Drupal renderer.
   */
  protected function getChannelElements() {
    $elements = [];
    $renderer = $this->getRenderer();

    foreach (views_rss_get('channel_elements') as $module => $module_channel_elements) {
      foreach ($module_channel_elements as $element => $definition) {
        [$element_namespace, $element_name] = views_rss_extract_element_names($element, 'core');

        // Try to fetch namespace value from view configuration.
        if (isset($this->options['channel'][$element_namespace][$module][$element_name])) {
          $element_value = $this->options['channel'][$element_namespace][$module][$element_name];
        }
        // Otherwise check if it was provided by element definition.
        elseif (isset($definition['default_value'])) {
          $element_value = $definition['default_value'];
        }
        else {
          $element_value = NULL;
        }

        // Start building XML channel element array compatible with
        // the Drupal renderer.
        $rss_element = [
          'key' => $element,
          'value' => $element_value,
        ];

        if (!empty($element_namespace) && $element_namespace != 'core') {
          $rss_element['namespace'] = $element_namespace;
        }

        // It might happen than a preprocess function will need to split one
        // element into multiple ones - this will for example happen for channel
        // <category> element, if multiple categories were provided (separated
        // by a comma) - they will need to be printed as multiple <category>
        // elements - therefore we need to work on array of RSS elements here.
        $rss_elements = [$rss_element];

        // Preprocess element value.
        if (isset($definition['preprocess functions']) && is_array($definition['preprocess functions'])) {
          foreach ($definition['preprocess functions'] as $preprocess_function) {
            if (function_exists($preprocess_function)) {
              $item_variables = [
                'elements' => &$rss_elements,
                'item' => $this->options['channel'],
                'view' => $this->view,
              ];
              $preprocess_function($item_variables);
            }
          }
        }

        foreach ($rss_elements as $rss_element) {
          // Keep certain elements from rendering in channel_elements array.
          // These have placeholders in the twig file.
          // @todo Find a better way of setting and passing these where they don't pass through rendering.
          $key = $rss_element['key'];
          if ($key == 'description') {
            // $this->option['description'] = $rss_element['value'];
          }
          elseif (in_array($key, ['title', 'description', 'link', 'language'])) {
            $elements[] = [
              // '#type' => 'html_tag',
              // '#tag' => $rss_element['key'],
              '#type' => 'markup',
              '#value' => $rss_element['value'],
            ];
          }
          // Build render arrays for the other channel_elements.
          // @todo Change these to isset() to support non-blank strings that
          // equate to FALSE in PHP, e.g. "false", "0".
          elseif (!empty($rss_element['value']) || !empty($rss_element['attributes'])) {
            $render_element = [
              '#type' => 'html_tag',
              '#tag' => $rss_element['key'],
            ];
            if (!empty($rss_element['value'])) {
              // Render arrays.
              if (is_array($rss_element['value'])) {
                $render_element['#value'] = method_exists($renderer, 'renderInIsolation') ?
                  $renderer->renderInIsolation($rss_element['value'], FALSE) :
                  // @todo use DeprecationHelper when D9 support will be dropped
                  // @phpstan-ignore method.deprecated
                  $renderer->renderPlain($rss_element['value'], FALSE);
              }
              // Simple values.
              else {
                $render_element['#value'] = $rss_element['value'];
              }
            }
            if (!empty($rss_element['attributes'])) {
              $render_element['#attributes'] = $rss_element['attributes'];
            }

            $elements[] = $render_element;
          }
        }
      }
    }

    return $elements;
  }

  /**
   * Get RSS feed description.
   *
   * @return string
   *   The string containing the description with the tokens replaced.
   */
  public function getDescription() {
    // @todo Need a better way of doing this.
    if (isset($this->options['channel']['core']['views_rss_core']['description'])
        && $this->options['channel']['core']['views_rss_core']['description'] != '') {
      $description = $this->options['channel']['core']['views_rss_core']['description'];

      // Allow substitutions from the first row.
      $description = $this->tokenizeValue($description, 0);
    }
    else {
      $description = $this->configFactory->get('system.site')->get('slogan');
    }

    return $description;
  }

  /**
   * Function getNamespaces.
   */
  protected function getNamespaces() {
    $namespaces = [];

    foreach (views_rss_get('namespaces') as $module => $module_namespaces) {
      foreach ($module_namespaces as $namespace => $definition) {

        // Check if definition provided through modules hooks
        // should be overwritten by module configuration.
        if (
          isset($this->options['namespaces'][$module][$namespace])
          && !empty($this->options['namespaces'][$module][$namespace])
        ) {
          $definition['uri'] = $this->options['namespaces'][$module][$namespace];
        }

        // Add namespace to feed array.
        if (!empty($definition['uri'])) {
          // Namespaces with prefix, for example xml:base="" or xmlns:dc="".
          if (!empty($definition['prefix'])) {
            $namespace_key = $definition['prefix'] . ':' . $namespace;
            $namespaces[$namespace_key] = $definition['uri'];
          }
          // Namespaces without prefix, for example: content="".
          else {
            $namespaces[$namespace] = $definition['uri'];
          }
        }

      }
    }

    return $namespaces;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = '';

    $this->namespaces = $this->getNamespaces();

    // Fetch any additional elements for the channel and merge in their
    // namespaces.
    $this->channel_elements = $this->getChannelElements();

    $rows = [];
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => $rows,
      '#attached' => [
        'http_header' => [
          [
            'Content-Type',
            'application/rss+xml; charset=utf-8',
          ],
        ],
      ],
    ];
    unset($this->view->row_index);
    return $build;
  }

}
