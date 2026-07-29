<?php

namespace Drupal\google_cse\Plugin\Search;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\search\Attribute\Search;
use Drupal\search\Plugin\ConfigurableSearchPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles searching for node entities using the Search module index.
 *
 * @SearchPlugin(
 *   id = "google_cse_search",
 *   title = @Translation("Google Programmable Search")
 * )
 */
#[Search(
  id: 'google_cse_search',
  title: new TranslatableMarkup('Google Programmable Search')
)]
class GoogleSearch extends ConfigurableSearchPluginBase implements AccessibleInterface {

  /**
   * {@inheritdoc}
   */
  protected $configuration;

  /**
   * The default query parameter key for searches.
   *
   * @var string
   */
  public static $defaultQueryKey = 'keys';

  /**
   * RequestStack object for getting requests.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * ModuleHandler services object.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $requestStack, ModuleHandlerInterface $moduleHandler, RendererInterface $renderer, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $requestStack;
    $this->moduleHandler = $moduleHandler;
    $this->renderer = $renderer;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('module_handler'),
      $container->get('renderer'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = [
      'cx' => '',
      'display_drupal_search' => TRUE,
      'results_prefix' => '',
      'results_suffix' => '',
      'results_searchbox_width' => 40,
      'results_display' => 'here',
      'results_accessibility' => TRUE,
      'custom_results_display' => 'results-only',
      'custom_css' => '',
      'query_key' => self::$defaultQueryKey,
      'watermark' => FALSE,
      'data_attributes' => [],
    ];
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $default = $this->defaultConfiguration();
    $form['cx'] = [
      '#title' => $this->t('Google Programmable Search Engine ID'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['cx'] ?? $default['cx'],
      '#description' => $this->t('Enter your @google.', [
        '@google' => Link::fromTextAndUrl('Google PSE ID', Url::fromUri('https://programmablesearchengine.google.com/cse/all'))->toString(),
      ]),
      '#required' => TRUE,
    ];

    $form['display_drupal_search'] = [
      '#title' => 'Display Drupal-provided search input',
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['display_drupal_search'] ?? $default['display_drupal_search'],
      '#description' => $this->t(
        'Leave this checked if you use the "Results only" layout. Uncheck this if you want to use the search input provided by Google (see layout options below). The Google search input includes an autocomplete feature, but it may necessitate site-specific theming that the Drupal-provided search input does not need.'),
    ];

    $form['watermark'] = [
      '#title' => 'Display Google watermark',
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['watermark'] ?? $default['watermark'],
      '#description' => $this->t('Programmable Search @link indicate that a Google watermark may be displayed.', [
        '@link' => Link::fromTextAndUrl('branding guidelines', Url::fromUri('https://support.google.com/programmable-search/answer/10026723'))->toString(),
      ]),
    ];

    $form['results_display'] = [
      '#title' => $this->t('Display search results'),
      '#type' => 'radios',
      '#default_value' => $this->configuration['results_display'] ?? $default['results_display'],
      '#options' => [
        'here' => $this->t('On this site (requires JavaScript)'),
        'google' => $this->t('On Google'),
      ],
      '#description' => $this->t('Search results can be displayed on this site (requires Javascript) or redirected to Google.'),
      '#required' => TRUE,
    ];

    $form['results_accessibility'] = [
      '#title' => $this->t('Add accessibility improvements to search results'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['results_accessibility'] ?? $default['results_accessibility'],
      '#description' => $this->t('Only applicable when results are displayed on this site (above). Currently, this adds <code>role="status"</code> to the search results summary, per <a href="https://www.w3.org/WAI/WCAG21/Techniques/aria/ARIA22">https://www.w3.org/WAI/WCAG21/Techniques/aria/ARIA22</a>'),
      '#states' => [
        'invisible' => [
          ':input[name="results_display"]' => ['value' => 'google'],
        ],
      ],
    ];
    $cx = isset($this->configuration['cx']) ? (string) $this->configuration['cx'] : '';
    $form['custom_results_display'] = [
      '#title' => $this->t('Layout of Search Engine'),
      '#type' => 'radios',
      '#default_value' => $this->configuration['custom_results_display'] ?? $default['custom_results_display'],
      '#options' => [
        'overlay' => $this->t('Overlay'),
        'full-width' => $this->t('Full width'),
        'two-column' => $this->t('Two column'),
        'compact' => $this->t('Compact'),
        'results-only' => $this->t('Results only'),
        'google-hosted' => $this->t('Google hosted'),
      ],
      '#description' => $this->t('Set the search engine layout, as found in the Layout tab of @url.', [
        '@url' => Link::fromTextAndUrl('Custom Search settings', Url::fromUri('https://www.google.com/cse/lookandfeel/layout?cx=' . $cx))->toString(),
      ]),
      '#required' => TRUE,
    ];

    $form['results_prefix'] = [
      '#title' => $this->t('Search results prefix text'),
      '#type' => 'textarea',
      '#cols' => 50,
      '#rows' => 4,
      '#description' => $this->t('Enter text to appear before the search results. Basic HTML is allowed.'),
      '#default_value' => $this->configuration['results_prefix'] ?? $default['results_prefix'],
    ];

    $form['results_suffix'] = [
      '#title' => $this->t('Search results suffix text'),
      '#type' => 'textarea',
      '#cols' => 50,
      '#rows' => 4,
      '#description' => $this->t('Enter text to appear after the search results. Basic HTML is allowed.'),
      '#default_value' => $this->configuration['results_suffix'] ?? $default['results_suffix'],
    ];
    $form['results_searchbox_width'] = [
      '#title' => $this->t('Search input width'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 500,
      '#size' => 6,
      '#description' => $this->t('Enter the desired width, in characters, of the searchbox on the Google Search block.'),
      '#default_value' => $this->configuration['results_searchbox_width'] ?? $default['results_searchbox_width'],
    ];

    $form['query_key'] = [
      '#title' => $this->t('Query parameter key'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['query_key'] ?? $default['query_key'],
      '#description' => $this->t('Optionally change the query parameter displayed in the URL. For example, change <code>https://example.com/search/google?keys=streets</code> to <code>https://example.com/search/google?query=streets</code>.',),
      '#required' => TRUE,
    ];

    $form['custom_css'] = [
      '#title' => $this->t('Stylesheet Override'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['custom_css'] ?? $default['custom_css'],
      '#description' => $this->t('Set a custom stylesheet to override or add any styles not allowed in the settings (such as "background-color: none;"). Include <span style="color:red; font-weight:bold;">!important</span> for overrides.<br/>Example: %replace', [
        '%replace' => '//replacewithrealsite.com/sites/all/modules/google_cse/default.css',
      ]),
    ];

    $form['data_attribute_header'] = [
      '#markup' => '<h3>Customizations</h3><p>You can use optional attributes to overwrite configurations created in the <a href="https://programmablesearchengine.google.com/cse/all">Programmable Search Engine control panel</a>. For example, including <code>data-lr="lang_fr"</code> will restrict results to documents written in French. Enter these data attributes as key/value pairs, where the key is <code>data-ATTRIBUTE_NAME</code> and the value is the ATTRIBUTE_VALUE. For a full list of available attributes, see <a href="https://developers.google.com/custom-search/docs/element#supported_attributes">https://developers.google.com/custom-search/docs/element#supported_attributes</a>.',
    ];

    $form['data_attributes'] = [
      '#type' => 'table',
      '#header' => [$this->t('Attribute Name'), $this->t('Attribute Value')],
    ];
    $data_attributes = $this->configuration['data_attributes'] ?? [];
    if (count($data_attributes) > 0) {
      $inc = 0;
      foreach ($data_attributes as $attribute) {
        $form['data_attributes'][$inc]['key'] = [
          '#type' => 'textfield',
          '#default_value' => $attribute['key'] ?? '',
        ];
        $form['data_attributes'][$inc]['value'] = [
          '#type' => 'textfield',
          '#default_value' => $attribute['value'] ?? '',
        ];
        $inc++;
      }
      // Make available one additional input each time.
      $form['data_attributes'][$inc]['key'] = [
        '#type' => 'textfield',
        '#default_value' => '',
        '#placeholder' => 'data-ATTRIBUTE_NAME',
      ];
      $form['data_attributes'][$inc]['value'] = [
        '#type' => 'textfield',
        '#default_value' => '',
        '#placeholder' => 'ATTRIBUTE_VALUE',
      ];
    }
    else {
      for ($inc = 0; $inc < 5; $inc++) {
        $form['data_attributes'][$inc]['key'] = [
          '#type' => 'textfield',
          '#default_value' => '',
          '#placeholder' => 'data-ATTRIBUTE_NAME',
        ];
        $form['data_attributes'][$inc]['value'] = [
          '#type' => 'textfield',
          '#default_value' => '',
          '#placeholder' => 'ATTRIBUTE_VALUE',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    foreach ($values['data_attributes'] as $inc => $data) {
      if (empty($data['key']) && empty($data['value'])) {
        unset($values['data_attributes'][$inc]);
        continue;
      }
      if (empty($data['key']) || empty($data['value'])) {
        $form_state->setErrorByName('data_attributes][' . $inc . '][key', $this->t('You must provide both a key and value for this attribute.'));
        break;
      }
      if (strpos($data['key'], 'data-') !== 0) {
        $form_state->setErrorByName('data_attributes][' . $inc . '][key', $this->t('Data attributes must begin with <code>data-</code>.'));
        break;
      }
      if (preg_match('/[^a-zA-Z0-9-_\.]/', $data['value'])) {
        $form_state->setErrorByName('data_attributes][' . $inc . '][value', $this->t('Attribute values may only contain alphanumerics, underscores, hyphens, and periods.'));
        break;
      }
      if (preg_match('/[^a-zA-Z0-9-_]/', $data['key'])) {
        $form_state->setErrorByName('data_attributes][' . $inc . '][key', $this->t('Attribute keys may only contain alphanumerics, underscores and hyphens.'));
        break;
      }
    }
    $form_state->setValue('data_attributes', $values['data_attributes']);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['cx'] = $values['cx'];
    $this->configuration['results_prefix'] = $values['results_prefix'];
    $this->configuration['results_suffix'] = $values['results_suffix'];
    $this->configuration['results_searchbox_width'] = intval($values['results_searchbox_width']);
    $this->configuration['results_display'] = $values['results_display'];
    $this->configuration['results_accessibility'] = $values['results_accessibility'];
    $this->configuration['custom_css'] = $values['custom_css'];
    $this->configuration['query_key'] = $values['query_key'];
    $this->configuration['custom_results_display'] = $values['custom_results_display'];
    $this->configuration['watermark'] = $values['watermark'];
    $this->configuration['display_drupal_search'] = $values['display_drupal_search'];
    $this->configuration['data_attributes'] = $values['data_attributes'];
    // Caches need to be cleared for drupalSettings to be reinitialized and the
    // SearchPage entity rendering to be rebuilt.
    drupal_flush_all_caches();
  }

  /**
   * {@inheritdoc}
   */
  public function setSearch($keywords, array $parameters, array $attributes) {
    if (empty($parameters['search_conditions'])) {
      $parameters['search_conditions'] = '';
    }
    parent::setSearch($keywords, $parameters, $attributes);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSearchUrlQuery(FormStateInterface $form_state) {
    // Grab the keywords entered in the form and put them as 'keys' in the GET.
    $query_key = $this->configuration['query_key'] ?? self::$defaultQueryKey;
    $keys = trim($form_state->getValue($query_key));
    $query = [$query_key => $keys];
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIf(!empty($account) && $account->hasPermission('search Google CSE'))->cachePerPermissions();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
    return $this;
  }

  /**
   * Verifies if the given parameters are valid enough to execute a search for.
   *
   * @return bool
   *   TRUE if there are keywords or search conditions in the query.
   */
  public function isSearchExecutable() {
    return (bool) ($this->keywords || !empty($this->searchParameters['search_conditions']));
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Present to satisfy Drupal/search/Plugin/SearchInterface.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildResults() {
    if (!$this->validRequest()) {
      return [];
    }
    // Results are primarily generated by the Google API,
    // through the google_cse_results template.
    // We also add dynamic JS to control the watermark,
    // the Google Search JS library itself, and
    // additional markup provided by the configuration.
    $output = [
      '#theme' => 'google_cse_results',
      '#attached' => [
        'library' => [
          'google_cse/googlecseWatermark',
          'google_cse/googlecseResults',
        ],
        'drupalSettings' => [
          'googlePSE' => [
            'displayWatermark' => $this->configuration['watermark'] ?? FALSE,
            'language' => $this->languageManager->getCurrentLanguage()->getId(),
          ],
        ],
      ],
    ];
    if (!array_key_exists('watermark', $this->configuration)) {
      $output['#attached']['library'][] = 'google_cse/noWatermark';
    }
    if (!isset($this->configuration['results_accessibility']) || $this->configuration['results_accessibility'] !== FALSE) {
      $output['#attached']['library'][] = 'google_cse/resultsAccessibility';
    }
    // Add the Google Programmable Search library itself, with ID as a param.
    $output['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#attributes' => [
          'async' => '',
          'src' => 'https://cse.google.com/cse.js?cx=' . $this->configuration['cx'],
        ],
      ],
      'google_cse_' . $this->configuration['cx'],
    ];
    if (!empty($this->configuration['custom_css'])) {
      $output['#attached']['library'][] = 'google_cse/customCSS_' . md5($this->configuration['custom_css']);
    }
    // Noscript message.
    $query_key = $this->configuration['query_key'] ?? self::$defaultQueryKey;
    $url = Url::fromUri('https://cse.google.com/cse', [
      'query' => [
        'cx' => $this->configuration['cx'],
        'q' => \Drupal::request()->query->get($query_key),
      ],
    ]);
    $output['#noscript'] = $this->t('@google, or enable JavaScript to view them here.', [
      '@google' => Link::fromTextAndUrl('View the results at Google', $url)->toString(),
    ]);
    if (!empty($this->configuration['results_prefix'])) {
      $output['#results_prefix']['#markup'] = $this->configuration['results_prefix'];
    }
    if (!empty($this->configuration['results_suffix'])) {
      $output['#results_suffix']['#markup'] = $this->configuration['results_suffix'];
    }
    $output = $this->buildGoogleMarkup($output);
    return [$output];
  }

  /**
   * Build out attributes based on configuration settings.
   *
   * @param array $output
   *   The render array as it has been defined.
   *
   * @return array
   *   The render array, with Google Search markup attributes.
   */
  public function buildGoogleMarkup(array $output) {
    $output['#primary_attributes'] = new Attribute();
    $output['#secondary_attributes'] = new Attribute();
    // Tell Google to use Drupal core search's 'keys' query parameter.
    // This is relevant for all choices except 'Google Hosted,' which
    // changes this value, below.
    $query_key = $this->configuration['query_key'] ?? self::$defaultQueryKey;
    $output['#primary_attributes']['data-queryParameterName'] = $query_key;
    if (!empty($this->configuration['data_attributes'])) {
      foreach ($this->configuration['data_attributes'] as $attribute) {
        $output['#primary_attributes'][$attribute['key']] = $attribute['value'];
        $output['#secondary_attributes'][$attribute['key']] = $attribute['value'];
      }
    }
    switch ($this->configuration['custom_results_display']) {
      case 'overlay':
      case 'compact':
      case 'full-width':
        $output['#primary_attributes']['class'] = 'gcse-search';
        unset($output['#secondary_attributes']);
        break;

      case 'two-page':
        $output['#primary_attributes']['class'] = 'gcse-searchbox-only';
        $output['#secondary_attributes']['class'] = 'gcse-searchresults-only';
        break;

      case 'two-column':
        $output['#primary_attributes']['class'] = 'gcse-searchbox';
        $output['#secondary_attributes']['class'] = 'gcse-searchresults';
        break;

      case 'results-only':
        $output['#primary_attributes']['class'] = 'gcse-searchresults-only';
        unset($output['#secondary_attributes']);
        break;

      case 'google-hosted':
        $output['#primary_attributes']['class'] = 'gcse-searchbox-only';
        // Google's standalone page expects the query string key to be 'q'.
        $output['#primary_attributes']['data-queryParameterName'] = 'q';
        unset($output['#secondary_attributes']);
        break;

      default:
        \Drupal::logger('google_cse')->critical('Invalid custom result display %display', ['%display' => $this->configuration['custom_results_display']]);
        break;
    }
    if ($this->configuration['results_display'] === 'google') {
      // Remove the text 'Your search yielded no results' in
      // the context of the Drupal site.
      $output['#primary_attributes']['data-noResultsString'] = '';
    }
    return $output;
  }

  /**
   * Validate GET parameters to avoid displaying inappropriate search results.
   */
  public function validRequest() {
    $request = \Drupal::request();
    $request_cx = $request->query->get('cx');
    $safesearch = $request->query->get('safe');
    $site_safesearch_setting = 'active';
    foreach ($this->configuration['data_attributes'] as $attribute) {
      if ($attribute['key'] === 'data-safeSearch') {
        $site_safesearch_setting = $attribute['value'];
      }
    }
    // 1. The search ID should either match the site configuration or be empty.
    // 2. The safesearch setting should either match the site configuration or
    // be empty (safe=true). This is to prevent hijacking of the URL in the
    // form "?safe=off".
    return (
      (empty($request_cx) || $request_cx == $this->configuration['cx']) &&
      (empty($safesearch) || $safesearch == $site_safesearch_setting)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function searchFormAlter(array &$form, FormStateInterface $form_state) {
    // Customize the search form on the Drupal search page.
    // This serves a different purpose than
    // google_cse_form_search_block_form_alter(), which modifies the Drupal
    // core search *block* form.
    if ($this->pluginId == 'google_cse_search') {
      if (!isset($this->configuration['display_drupal_search']) || $this->configuration['display_drupal_search'] === 1 || $this->configuration['display_drupal_search'] === TRUE) {
        $query_key = $this->configuration['query_key'] ?? self::$defaultQueryKey;
        $form['#attributes']['class'][] = 'google-cse';
        $form['basic']['keys']['#title'] = $this->t('Enter your keywords');
        if ($this->configuration['results_searchbox_width']) {
          $form['basic']['keys']['#size'] = intval($this->configuration['results_searchbox_width']);
        }
        if ($this->configuration['results_display'] === 'google') {
          // This search has been configured to display results on Google.
          // Redirect data there.
          $form['#action'] = 'https://cse.google.com/cse';
          $form['#method'] = 'get';
          $form['basic']['cx'] = [
            '#type' => 'hidden',
            '#value' => $this->configuration['cx'],
          ];
          $form['basic']['q'] = $form['basic']['keys'];
          $form['basic']['q']['#weight'] = -10;
          unset($form['basic']['keys']);
        }
        elseif ($query_key !== 'keys') {
          // Customize query key, if configured.
          $form['basic'][$query_key] = $form['basic']['keys'];
          unset($form['basic']['keys']);
        }
      }
      else {
        // The configuration says the Drupal search input should not display.
        // This usually means the configuration is set to display the
        // Google-provided input.
        unset($form['basic']);
        unset($form['help_link']);
      }
    }
  }

}
