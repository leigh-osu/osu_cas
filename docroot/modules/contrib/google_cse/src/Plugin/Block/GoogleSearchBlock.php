<?php

namespace Drupal\google_cse\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\google_cse\Plugin\Search\GoogleSearch;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Google Programmable Search' block.
 *
 * @Block(
 *   id = "google_cse",
 *   admin_label = @Translation("Google Programmable Search"),
 *   category = @Translation("Forms"),
 * )
 */
#[Block(
  id: 'google_cse',
  admin_label: new TranslatableMarkup('Google Programmable Search'),
  category: new TranslatableMarkup('Forms')
)]
class GoogleSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Stores the configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Form builder will be used via Dependency Injection.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new GoogleCSEBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'search Google CSE');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label' => $this->t('Search'),
      'search_id' => '',
      'search_type' => 'drupal',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $search_implementations = $this->entityTypeManager->getStorage('search_page')->loadMultiple();
    // It is possible to have more than one Google search configuration.
    // If there is more than one Google search entity, display them as a select
    // list for this block configuration.
    $options = [];
    foreach ($search_implementations as $search) {
      if ($search->getPlugin()->getPluginId() !== 'google_cse_search') {
        continue;
      }
      $options[$search->id()] = $search->label();
    }
    if (count($options) > 1) {
      $form['search_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Search instance'),
        '#options' => $options,
        '#default_value' => $this->configuration['search_id'],
      ];
    }
    elseif (count($options) === 1) {
      $value = array_keys($options);
      $form['search_id'] = [
        '#type' => 'textfield',
        '#disabled' => TRUE,
        '#title' => $this->t('Search instance'),
        '#description' => $this->t('This field is disabled because only one search configuration exists.'),
        '#default_value' => reset($value),
      ];
    }
    // Allow the user to select whether they want the Drupal search input
    // or the Google PSE one (e.g., for autocomplete).
    $search_types = [
      'drupal' => 'Drupal',
      'google' => 'Google',
    ];
    $form['search_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Search input type'),
      '#description' => $this->t('Use the Google search input if you want Google-provided features such as search autocomplete.'),
      '#options' => $search_types,
      '#default_value' => $this->configuration['search_type'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    // Process the block's submission handling if no errors occurred only.
    if (!$form_state->getErrors()) {
      $this->configuration['search_id'] = $form_state
        ->getValue('search_id');
      $this->configuration['search_type'] = $form_state
        ->getValue('search_type');
      $this->blockSubmit($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $search_id = $this->configuration['search_id'];
    $search_type = $this->configuration['search_type'];
    $entity = $this->entityTypeManager->getStorage('search_page')->load($search_id);
    if ($entity === NULL) {
      // This conditional handles search configurations that were deleted.
      return [];
    }
    $plugin = $entity->getPlugin();
    $google_markup = $plugin->buildResults();
    $config = $this->configFactory->get('search.page.' . $search_id);
    $settings = $config->get('configuration');
    $query = $settings['query_key'] ?? GoogleSearch::$defaultQueryKey;
    $form['search'] = ['#markup' => '<div class="gcse-searchbox-only" data-resultsUrl="/search/' . $entity->get('path') . '" data-queryParameterName="' . $query . '"></div>'];
    if ($search_type === 'google') {
      $form['search'] = ['#markup' => '<div class="gcse-searchbox-only" data-resultsUrl="/search/' . $entity->get('path') . '" data-queryParameterName="' . $query . '"></div>'];
      // Add the Google Programmable Search library itself, with ID as a param.
      $form['#attached']['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#attributes' => [
            'async' => '',
            'src' => 'https://cse.google.com/cse.js?cx=' . $settings['cx'],
          ],
        ],
        'google_cse_' . $settings['cx'],
      ];
      return $form;
    }
    if (isset($settings['display_drupal_search']) && $settings['display_drupal_search'] === 0) {
      // The configuration indicates the Drupal search input shouldn't display.
      // This usually means the configuration is set to display the
      // Google-provided input.
      return $google_markup;
    }
    $form_state = new FormState();
    $form_state->setValue('google_search_settings', $settings);
    $search_input = $this->formBuilder->buildForm('Drupal\google_cse\Form\GoogleCSESearchBoxForm', $form_state);
    return [$search_input, $google_markup];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      parent::getCacheTags(),
      $this->configFactory->get('search.page.google_cse_search')->getCacheTags()
    );
  }

}
