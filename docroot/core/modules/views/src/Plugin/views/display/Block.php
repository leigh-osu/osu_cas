<?php

namespace Drupal\views\Plugin\views\display;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Component\Plugin\ContextAwarePluginInterface;
use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\views\Attribute\ViewsDisplay;
use Drupal\views\Plugin\Block\ViewsBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The plugin that handles a block.
 *
 * @ingroup views_display_plugins
 *
 * @see \Drupal\views\Plugin\Block\ViewsBlock
 * @see \Drupal\views\Plugin\Derivative\ViewsBlock
 */
#[ViewsDisplay(
  id: "block",
  title: new TranslatableMarkup("Block"),
  help: new TranslatableMarkup("Display the view as a block."),
  admin: new TranslatableMarkup("Block"),
  theme: "views_view",
  register_theme: FALSE,
  uses_hook_block: TRUE,
  contextual_links_locations: ["block"]
)]
class Block extends DisplayPluginBase {

  /**
   * Whether the display allows attachments.
   *
   * @var bool
   */
  protected $usesAttachments = TRUE;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The key/value manager service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValue;

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * The plugin context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * A hashed key of the key/value entry that holds block instance settings.
   *
   * @var string
   */
  protected $blockConfigKey;

  /**
   * Constructs a new Block instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value
   *   The key/value manager service.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The ContextHandler for applying contexts to conditions properly.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, BlockManagerInterface $block_manager, KeyValueFactoryInterface $key_value = NULL, ContextRepositoryInterface $context_repository = NULL, ContextHandlerInterface $context_handler = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->blockManager = $block_manager;
    $this->keyValue = $key_value ?: \Drupal::service('keyvalue');
    $this->contextRepository = $context_repository ?: \Drupal::service('context.repository');
    $this->contextHandler = $context_handler ?: \Drupal::service('context.handler');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
      $container->get('keyvalue'),
      $container->get('context.repository'),
      $container->get('context.handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['block_description'] = ['default' => ''];
    $options['block_category'] = ['default' => $this->t('Lists (Views)')];
    $options['block_hide_empty'] = ['default' => FALSE];

    $options['allow'] = [
      'contains' => [
        'items_per_page' => ['default' => 'items_per_page'],
      ],
    ];

    return $options;
  }

  /**
   * Returns plugin-specific settings for the block.
   *
   * @param array $settings
   *   The settings of the block.
   *
   * @return array
   *   An array of block-specific settings to override the defaults provided in
   *   \Drupal\views\Plugin\Block\ViewsBlock::defaultConfiguration().
   *
   * @see \Drupal\views\Plugin\Block\ViewsBlock::defaultConfiguration()
   */
  public function blockSettings(array $settings) {
    $settings['items_per_page'] = 'none';
    return $settings;
  }

  /**
   * The display block handler returns the structure necessary for a block.
   */
  public function execute() {
    // Prior to this being called, the $view should already be set to this
    // display, and arguments should be set on the view.
    $element = $this->view->render();
    if ($this->outputIsEmpty() && $this->getOption('block_hide_empty') && empty($this->view->style_plugin->definition['even empty'])) {
      return [];
    }
    else {
      return $element;
    }
  }

  /**
   * Provide the summary for page options in the views UI.
   *
   * This output is returned as an array.
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    $categories['block'] = [
      'title' => $this->t('Block settings'),
      'column' => 'second',
      'build' => [
        '#weight' => -10,
      ],
    ];

    $block_description = strip_tags($this->getOption('block_description'));
    if (empty($block_description)) {
      $block_description = $this->t('None');
    }
    $block_category = $this->getOption('block_category');

    $options['block_description'] = [
      'category' => 'block',
      'title' => $this->t('Block name'),
      'value' => Unicode::truncate($block_description, 24, FALSE, TRUE),
    ];
    $options['block_category'] = [
      'category' => 'block',
      'title' => $this->t('Block category'),
      'value' => Unicode::truncate($block_category, 24, FALSE, TRUE),
    ];

    $filtered_allow = array_filter($this->getOption('allow'));

    $options['allow'] = [
      'category' => 'block',
      'title' => $this->t('Allow settings'),
      'value' => empty($filtered_allow) ? $this->t('None') : $this->t('Items per page'),
    ];

    $options['block_hide_empty'] = [
      'category' => 'other',
      'title' => $this->t('Hide block if the view output is empty'),
      'value' => $this->getOption('block_hide_empty') ? $this->t('Yes') : $this->t('No'),
    ];
  }

  /**
   * Provide the default form for setting options.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'block_description':
        $form['#title'] .= $this->t('Block admin description');
        $form['block_description'] = [
          '#type' => 'textfield',
          '#description' => $this->t('This will appear as the name of this block in administer >> structure >> blocks.'),
          '#default_value' => $this->getOption('block_description'),
        ];
        break;

      case 'block_category':
        $form['#title'] .= $this->t('Block category');
        $form['block_category'] = [
          '#type' => 'textfield',
          '#autocomplete_route_name' => 'block.category_autocomplete',
          '#description' => $this->t('The category this block will appear under on the <a href=":href">blocks placement page</a>.', [':href' => Url::fromRoute('block.admin_display')->toString()]),
          '#default_value' => $this->getOption('block_category'),
        ];
        break;

      case 'block_hide_empty':
        $form['#title'] .= $this->t('Block empty settings');

        $form['block_hide_empty'] = [
          '#title' => $this->t('Hide block if no result/empty text'),
          '#type' => 'checkbox',
          '#description' => $this->t('Hide the block if there is no result and no empty text and no header/footer which is shown on empty result'),
          '#default_value' => $this->getOption('block_hide_empty'),
        ];
        break;

      case 'exposed_form_options':
        $this->view->initHandlers();
        if (!$this->usesExposed() && parent::usesExposed()) {
          $form['exposed_form_options']['warning'] = [
            '#weight' => -10,
            '#markup' => '<div class="messages messages--warning">' . $this->t('Exposed filters in block displays require "Use AJAX" to be set to work correctly.') . '</div>',
          ];
        }
        break;

      case 'allow':
        $form['#title'] .= $this->t('Allow settings in the block configuration');

        $options = [
          'items_per_page' => $this->t('Items per page'),
        ];

        $allow = array_keys(array_filter($this->getOption('allow')));
        $form['allow'] = [
          '#type' => 'checkboxes',
          '#default_value' => $allow,
          '#options' => $options,
        ];
        break;
    }
  }

  /**
   * Perform any necessary changes to the form values prior to storage.
   *
   * There is no need for this function to actually store the data.
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    $section = $form_state->get('section');
    switch ($section) {
      case 'block_description':
      case 'block_category':
      case 'allow':
      case 'block_hide_empty':
        $this->setOption($section, $form_state->getValue($section));
        break;
    }
  }

  /**
   * Adds the configuration form elements specific to this views block plugin.
   *
   * This method allows block instances to override the views items_per_page.
   *
   * @param \Drupal\views\Plugin\Block\ViewsBlock $block
   *   The ViewsBlock plugin.
   * @param array $form
   *   The form definition array for the block configuration form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The renderable form array representing the entire configuration form.
   *
   * @see \Drupal\views\Plugin\Block\ViewsBlock::blockForm()
   */
  public function blockForm(ViewsBlock $block, array &$form, FormStateInterface $form_state) {
    $allow_settings = array_filter($this->getOption('allow'));

    $block_configuration = $block->getConfiguration();

    foreach ($allow_settings as $type => $enabled) {
      if (empty($enabled)) {
        continue;
      }
      switch ($type) {
        case 'items_per_page':
          $form['override']['items_per_page'] = [
            '#type' => 'select',
            '#title' => $this->t('Items per block'),
            '#options' => [
              'none' => $this->t('@count (default setting)', ['@count' => $this->getPlugin('pager')->getItemsPerPage()]),
              1 => 1,
              2 => 2,
              3 => 3,
              4 => 4,
              5 => 5,
              6 => 6,
              10 => 10,
              12 => 12,
              20 => 20,
              24 => 24,
              40 => 40,
              48 => 48,
            ],
            '#default_value' => $block_configuration['items_per_page'],
          ];
          break;
      }
    }

    return $form;
  }

  /**
   * Handles form validation for the views block configuration form.
   *
   * @param \Drupal\views\Plugin\Block\ViewsBlock $block
   *   The ViewsBlock plugin.
   * @param array $form
   *   The form definition array for the block configuration form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\views\Plugin\Block\ViewsBlock::blockValidate()
   */
  public function blockValidate(ViewsBlock $block, array $form, FormStateInterface $form_state) {
  }

  /**
   * Handles form submission for the views block configuration form.
   *
   * @param \Drupal\views\Plugin\Block\ViewsBlock $block
   *   The ViewsBlock plugin.
   * @param array $form
   *   The form definition array for the full block configuration form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\views\Plugin\Block\ViewsBlock::blockSubmit()
   */
  public function blockSubmit(ViewsBlock $block, $form, FormStateInterface $form_state) {
    if ($items_per_page = $form_state->getValue(['override', 'items_per_page'])) {
      $block->setConfigurationValue('items_per_page', $items_per_page);
    }
    $form_state->unsetValue(['override', 'items_per_page']);
  }

  /**
   * Allows to change the display settings right before executing the block.
   *
   * @param \Drupal\views\Plugin\Block\ViewsBlock $block
   *   The block plugin for views displays.
   */
  public function preBlockBuild(ViewsBlock $block) {
    $config = $block->getConfiguration();

    // If this block is being rebuilt as part of an AJAX call, the AJAX handler
    // does not have block instance settings and context information available.
    // Because of that, the first time this block is rendered (normally during
    // a non-AJAX request, but it could be AJAX as well), we store the block
    // instance overrides in the key/value store, to be retrieved when
    // subsequent AJAX calls happen. This will be possible as long as all
    // following calls pass along the 'block_config_key' query param and it
    // matches the key we are generating here for this view+display combination.
    // See \Drupal\views\Plugin\views\display\Block::preview().
    // See \Drupal\views\Plugin\views\display\Block::getConfigurationFromHashedKey().
    $key = $this->view->getRequest()->query->get('block_config_key');
    if (empty($key)) {
      // Calculate a brand new key.
      $this->blockConfigKey = $this->calculateConfigurationHash($config);
      $key_value_storage = $this->keyValue->get('views_block_overrides');
      $view_id = $this->view->id();
      if (!$key_value_storage->has($view_id)) {
        $key_value_storage->set($view_id, [$this->blockConfigKey => $config]);
      }
      else {
        $existing_config = $key_value_storage->get($view_id);
        if (!isset($existing_config[$this->blockConfigKey])) {
          $existing_config[$this->blockConfigKey] = $config;
          $key_value_storage->set($view_id, $existing_config);
        }
      }
    }
    elseif ($this->getConfigurationFromHashedKey($key)) {
      // If we can retrieve valid configuration from the received key, persist
      // it between requests.
      $this->blockConfigKey = $key;
    }
    else {
      // If the received key does not validate, mark the build as failed, which
      // will abort the rendering process.
      // See \Drupal\views\ViewExecutable::render().
      $this->view->build_info['fail'] = TRUE;
      return;
    }

    if ($config['items_per_page'] !== 'none') {
      $this->view->setItemsPerPage($config['items_per_page']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preparePreview() {
    // In AJAX requests, we have to figure out the block config ourselves and
    // prepare the view using that.
    if ($block_instance = $this->getBlockFromAjaxRequest()) {
      $this->preBlockBuild($block_instance);
    }
  }

  /**
   * Returns a configured views block plugin instance on an AJAX request.
   *
   * @return \Drupal\views\Plugin\Block\ViewsBlock|null
   *   The views block or NULL if this is not an AJAX request or the block
   *   can't be instantiated.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  protected function getBlockFromAjaxRequest(): ViewsBlock|null {
    if (!$this->view->getRequest()->isXmlHttpRequest()) {
      return NULL;
    }

    // We expect to receive here the "block_config_key" parameter, which will
    // allow us to retrieve the block config from the key/value store.
    $query_args = $this->view->getRequest()->query->all();
    // In order to have exposed filters submissions preserve the query args as
    // well, they are injected in the 'view_query' param. We merge them all
    // together here.
    if (!empty($query_args['view_query'])) {
      $parsed_view_query = UrlHelper::parse('?' . $query_args['view_query']);
      $query_args += $parsed_view_query['query'];
    }
    if (empty($query_args['block_config_key'])) {
      return NULL;
    }

    // Retrieve the block configuration values from the key/value store, ensure
    // that is been generated for the same view.
    $configuration = $this->getConfigurationFromHashedKey($query_args['block_config_key']);
    if ($configuration === FALSE) {
      throw new AccessDeniedHttpException('Invalid block config key.');
    }

    // Create a block instance with those settings.
    /** @var \Drupal\views\Plugin\Block\ViewsBlock $block_instance */
    try {
      $block_instance = $this->blockManager->createInstance($configuration['id'], $configuration);
      $plugin_definition = $block_instance->getPluginDefinition();
      if ($plugin_definition['id'] === 'broken') {
        return NULL;
      }
      if ($block_instance instanceof ContextAwarePluginInterface) {
        $context_mapping = $block_instance->getContextMapping();
        $context_mapping = array_filter($context_mapping, function ($x) {
          return $x !== 'layout_builder.entity';
        });
        $contexts = $this->contextRepository->getRuntimeContexts($context_mapping);
        $this->contextHandler->applyContextMapping($block_instance, $contexts);
        return $block_instance;
      }
    }
    catch (\Exception $e) {
      return NULL;
    }
    return NULL;
  }

  /**
   * Retrieve the stored configuration from a given hashed key.
   *
   * @param string $key
   *   The hashed key.
   *
   * @return array|false
   *   The configuration array if the received key is valid and matches with
   *   the view/display being executed, FALSE otherwise.
   */
  protected function getConfigurationFromHashedKey($key) {
    $configuration = $this->keyValue->get('views_block_overrides')->get($this->view->id());
    if ($configuration && !empty($configuration[$key]['id'])) {
      $calculated_hash = $this->calculateConfigurationHash($configuration[$key]);
      if ($calculated_hash === $key) {
        return $configuration[$key];
      }
    }
    return FALSE;
  }

  /**
   * Generates a hash for the given configuration and current view/display.
   *
   * @param array $configuration
   *   The block configuration.
   *
   * @return string
   *   The generated hash.
   */
  protected function calculateConfigurationHash(array $configuration) {
    return $this->view->current_display . ':' . Crypt::hmacBase64(serialize($configuration), Settings::getHashSalt());
  }

  /**
   * {@inheritdoc}
   */
  public function elementPreRender(array $element) {
    $element = parent::elementPreRender($element);
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $element['#view'];

    // Add the overrides key as a query param, so subsequent AJAX calls for
    // other pages have this info available.
    if (!empty($element['#pager']) && !empty($this->blockConfigKey)) {
      $element['#pager']['#parameters']['block_config_key'] = $this->blockConfigKey;
    }

    // Do the same for exposed filters. However, once here the submission
    // happens in a POST request, we inject our overrides key in the view JS
    // settings, that will be appended to the real query string later in the
    // AJAX behavior. See views_views_pre_render() and Drupal.views.ajaxView
    // for more information.
    if ($view->ajaxEnabled() && !empty($view->exposed_widgets) && empty($view->is_attachment) && empty($view->live_preview)) {
      $view_query = "block_config_key=$this->blockConfigKey";
      $view->element['#attached']['drupalSettings']['views']['ajaxViews']['views_dom_id:' . $view->dom_id]['view_query'] = $view_query;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function usesExposedFormInBlock() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function remove() {
    parent::remove();

    if ($this->entityTypeManager->hasDefinition('block')) {
      $plugin_id = 'views_block:' . $this->view->storage->id() . '-' . $this->display['id'];
      foreach ($this->entityTypeManager->getStorage('block')->loadByProperties(['plugin' => $plugin_id]) as $block) {
        $block->delete();
      }
    }
    if ($this->blockManager instanceof CachedDiscoveryInterface) {
      $this->blockManager->clearCachedDefinitions();
    }
  }

}
