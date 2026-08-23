<?php

declare(strict_types=1);

namespace Drupal\entityreference_filter\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\entityreference_filter\Service\FilterArgumentsResolver;
use Drupal\entityreference_filter\Service\FilterDependencyResolver;
use Drupal\entityreference_filter\Service\ReferenceViewOptionsBuilder;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by entity id using items got from entity reference view.
 *
 * @see \Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid
 */
#[ViewsFilter("entityreference_filter_view_result")]
class EntityReferenceFilterViewResult extends ManyToOne {

  /**
   * Sentinel filter value matching no entity for a non-exposed empty list.
   *
   * '0' is never a real entity id, so the generated IN() condition returns no
   * rows when the reference view produced no options.
   */
  protected const NO_RESULTS_VALUE = '0';

  /**
   * Stores the exposed input for this filter.
   *
   * @var mixed
   */
  protected mixed $validatedExposedInput = NULL;

  /**
   * Config view storage.
   */
  protected EntityStorageInterface $viewStorage;

  /**
   * Entity Type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The filter arguments resolver.
   */
  protected FilterArgumentsResolver $filterArgumentsResolver;

  /**
   * The reference view options builder.
   */
  protected ReferenceViewOptionsBuilder $referenceViewOptionsBuilder;

  /**
   * The filter dependency resolver.
   */
  protected FilterDependencyResolver $filterDependencyResolver;

  /**
   * Lazily built map of base/data table name to entity list cache tags.
   *
   * @var array[]|null
   */
  protected ?array $tagsByTable = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->viewStorage = $instance->entityTypeManager->getStorage('view');
    $instance->filterArgumentsResolver = $container->get(FilterArgumentsResolver::class);
    $instance->referenceViewOptionsBuilder = $container->get(ReferenceViewOptionsBuilder::class);
    $instance->filterDependencyResolver = $container->get(FilterDependencyResolver::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['type'] = ['default' => 'select'];
    $options['reference_display'] = ['default' => ''];
    $options['reference_arguments'] = ['default' => ''];
    $options['hide_empty_filter'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtraOptions() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {
    // @todo autocomplete support
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Selection type'),
      '#options' => [
        'select' => $this->t('Dropdown'),
        'textfield' => $this->t('Autocomplete'),
      ],
      '#default_value' => $this->options['type'],
      '#disabled' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);

    $form['hide_empty_filter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide empty filter'),
      '#description' => $this->t('Hide the exposed widget if the entity list is empty.'),
      '#default_value' => $this->options['hide_empty_filter'],
    ];

    // Hide useless field.
    $form['expose']['reduce']['#access'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }

    $this->valueOptions = $this->getConfiguredViewsOptions();

    return $this->valueOptions;
  }

  /**
   * Returns options for the selected filter.
   *
   * @return array
   *   Options list.
   */
  public function getConfiguredViewsOptions(): array {
    return $this->referenceViewOptionsBuilder->buildOptions(
      (string) ($this->options['reference_display'] ?? ''),
      $this->getFilterArgs()
    ) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $new_args = $form_state->getValue(['options', 'reference_arguments']) ?? '';

    // Only dynamic [identifier] arguments can create a dependency cycle.
    if (!str_contains($new_args, '[')) {
      return;
    }

    $current_key = $this->options['id'];
    $display_filters = $this->view->display_handler->getOption('filters') ?? [];

    $filters = [];
    foreach ($display_filters as $key => $config) {
      $filters[$key] = [
        'reference_arguments' => $config['reference_arguments'] ?? '',
        'identifier' => $config['expose']['identifier'] ?? $key,
      ];
    }
    // Overlay the value being edited (it may not be saved yet).
    $filters[$current_key]['reference_arguments'] = $new_args;

    $graph = $this->filterDependencyResolver->buildGraph($filters);
    $cycle = $this->filterDependencyResolver->detectCycle($graph);

    if ($cycle !== []) {
      $element = $form['reference_arguments'] ?? $form;
      $form_state->setError($element, $this->t('The filter arguments create a circular dependency between exposed filters: @chain. Remove the cycle.', [
        '@chain' => implode(' → ', $cycle),
      ]));
    }
  }

  /**
   * Get the calculated filter arguments.
   *
   * @return array
   *   Calculated arguments.
   */
  protected function getFilterArgs(): array {
    return $this->filterArgumentsResolver->resolve(
      (string) ($this->options['reference_arguments'] ?? ''),
      $this->getViewArgs(),
      $this->getViewContextArgs(),
      function (string $filter_name) {
        // User input takes precedence over the default values configured in
        // the exposed filter settings.
        $input = $this->view->getExposedInput();
        if (isset($input[$filter_name])) {
          return $input[$filter_name];
        }
        foreach ($this->view->filter ?? [] as $filter_handler) {
          if ($filter_handler->isExposed() && ($filter_handler->options['expose']['identifier'] ?? NULL) === $filter_name) {
            return $filter_handler->value;
          }
        }
        return NULL;
      }
    );
  }

  /**
   * Get current view arguments.
   *
   * @return array
   *   View arguments.
   */
  protected function getViewArgs(): array {
    return $this->view->args;
  }

  /**
   * Get current view contextual arguments.
   *
   * @return array
   *   View contextual arguments.
   */
  protected function getViewContextArgs(): array {
    $args = [];
    $arguments = $this->view->argument ?? [];

    foreach ($arguments as $handler) {
      $args[] = $handler->getValue();
    }

    return $args;
  }

  /**
   * After build form callback for exposed form with entity reference filters.
   */
  public function afterBuild(array $element, FormStateInterface $form_state): array {
    $identifier = $this->options['expose']['identifier'];
    $form_id = $element['#id'];
    $controlling_filters = $this->filterArgumentsResolver->getControllingFilters((string) ($this->options['reference_arguments'] ?? ''));

    // Prevent Firefox from remembering values between page reloads.
    foreach ($controlling_filters as $filter) {
      if (isset($element[$filter])) {
        if (!isset($element[$filter]['#attributes'])) {
          $element[$filter]['#attributes'] = [];
        }
        $element[$filter]['#attributes']['autocomplete'] = 'off';
        foreach (Element::children($element[$filter]) as $child) {
          if (!isset($element[$filter][$child]['#attributes'])) {
            $element[$filter][$child]['#attributes'] = [];
          }
          $element[$filter][$child]['#attributes']['autocomplete'] = 'off';
        }
      }
    }

    /** @var \Drupal\views\Plugin\views\exposed_form\ExposedFormPluginInterface $exposed_plugin **/
    $exposed_plugin = $this->view->display_handler->getPlugin('exposed_form');
    $exposed_plugin_options = $exposed_plugin->options ?? NULL;
    $autosubmit = $exposed_plugin_options['bef']['general']['autosubmit'] ?? FALSE;

    // Send dependent filters settings into drupalSettings.
    if (!$autosubmit && !empty($controlling_filters)) {
      $element['#attached']['drupalSettings']['entityreference_filter'][$form_id]['view'] = [
        'view_name' => $this->view->storage->id(),
        'view_display_id' => $this->view->current_display,
        'view_args' => implode('/', $this->getViewArgs()),
        'view_context_args' => $this->getViewContextArgs(),
        'ajax_path' => Url::fromRoute('entityreference_filter.ajax')->toString(),
      ];
      $element['#attached']['drupalSettings']['entityreference_filter'][$form_id]['dependent_filters_data'][$identifier] = $controlling_filters;
    }

    return $element;
  }

  /**
   * Restores the reference view order of this filter's options after BEF.
   *
   * The reference view is the single source of truth for both the option set
   * and their order. Better Exposed Filters can reorder the list (its option
   * rewrite pushes rewritten options to the front, and "Sort filter options"
   * re-sorts them alphabetically), which conflicts with the view order. We
   * therefore reorder back to the reference view order on every render while
   * keeping the BEF-rewritten labels; to change the order, sort the view.
   */
  public function restoreReferenceOrder(array $element, FormStateInterface $form_state): array {
    $identifier = $this->options['expose']['identifier'] ?? '';
    if ($identifier === '' || !isset($element[$identifier]['#options']) || !is_array($element[$identifier]['#options'])) {
      return $element;
    }

    $current = $element[$identifier]['#options'];
    $source_keys = array_map('strval', array_keys($this->getValueOptions()));

    // Keep any extra leading options (such as '- Any -') in place, then append
    // the reference view options in their source order.
    $ordered = [];
    foreach ($current as $key => $label) {
      if (!in_array((string) $key, $source_keys, TRUE)) {
        $ordered[$key] = $label;
      }
    }
    foreach (array_keys($this->getValueOptions()) as $key) {
      if (array_key_exists($key, $current)) {
        $ordered[$key] = $current[$key];
      }
    }

    $element[$identifier]['#options'] = $ordered;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $values = $this->getValueOptions();
    $exposed = $form_state->get('exposed');
    $filter_is_required = $this->options['expose']['required'];
    $filter_is_multiple = $this->options['expose']['multiple'];
    $filter_empty_hide = $this->options['hide_empty_filter'];

    // Autocomplete widget.
    if ($this->options['type'] === 'textfield') {
      // @todo autocomplete widget support
    }
    // Select list widget.
    else {
      $default_value = (array) $this->value;

      // Run time.
      if ($exposed) {
        $identifier = $this->options['expose']['identifier'];
        $user_input = $form_state->getUserInput();

        // This filter depends on other filters dynamically: attach the JS and
        // rebuild its drupalSettings on every form rebuild.
        if (isset($this->options['reference_arguments']) && str_contains($this->options['reference_arguments'], '[')) {
          $form['#attached']['library'][] = 'entityreference_filter/entityreference_filter';
          $form['#after_build'][] = [$this, 'afterBuild'];
        }

        // Keep the reference view order.
        $form['#after_build'][] = [$this, 'restoreReferenceOrder'];

        // Recalculate the selected value against the available options, using
        // the same rule the AJAX cascade applies on the server.
        $current = $user_input[$identifier] ?? [];
        if (!is_array($current)) {
          $current = [$current];
        }
        $default_value = $this->filterDependencyResolver->computeEffectiveValue(
          $current,
          array_keys($values),
          (bool) $filter_is_required,
          (bool) $filter_is_multiple,
        );
      }

      $form['value'] = [
        '#type' => 'select',
        '#title' => $this->t('Reference filter'),
        '#multiple' => TRUE,
        '#options' => $values,
        '#size' => max(1, min(9, count($values))),
        '#default_value' => $default_value,
      ];

      // Set user input.
      if ($exposed && isset($identifier)) {
        $user_input[$identifier] = $default_value;
        $form_state->setUserInput($user_input);
      }
    }

    // Hide filter with empty options.
    if (empty($values) && $exposed && $filter_empty_hide) {
      $form['value']['#prefix'] = '<div class="hidden">';
      $form['value']['#suffix'] = '</div>';
    }

    // Views UI. Options form.
    if (!$exposed) {

      $options = [];
      $views_ids = [];

      // Don't show value selection widget.
      $form['value']['#access'] = FALSE;

      $filter_base_table = !empty($this->definition['filter_base_table']) ? $this->definition['filter_base_table'] : NULL;

      // Filter views that list the entity type we want and group the separate
      // displays by view.
      if ($filter_base_table) {
        $views_ids = $this->viewStorage->getQuery()
          ->accessCheck(FALSE)
          ->condition('status', TRUE)
          ->condition('display.*.display_plugin', 'entity_reference')
          ->condition('base_table', $filter_base_table)
          ->execute();
      }

      foreach ($this->viewStorage->loadMultiple($views_ids) as $view) {
        // Check each display to see if it meets the criteria and it is enabled.
        foreach ($view->get('display') as $display_id => $display) {
          // If the key doesn't exist, enabled is assumed.
          $enabled = !empty($display['display_options']['enabled']) || !array_key_exists('enabled', $display['display_options']);
          if ($enabled && $display['display_plugin'] === 'entity_reference') {
            $options[$view->id() . ':' . $display_id] = $view->label() . ' - ' . $display['display_title'];
          }
        }
      }

      $show_reference_arguments_field = TRUE;
      $description = '<p>' . $this->t('Choose the view and display that select the entities that can be referenced. Only views with a display of type "Entity Reference" are eligible.') . '</p>';
      if (empty($options)) {
        $options = [$this->options['reference_display'] => $this->t('None')] + $options;
        $warning = '<em>' . $this->t('No views to use. At first, create a view display type "Entity Reference" with the same entity type as default filter values.') . '</em>';
        $description = $warning;
        $show_reference_arguments_field = FALSE;
      }

      $form['reference_display'] = [
        '#type' => 'select',
        '#title' => $this->t('View used to select the entities'),
        '#required' => TRUE,
        '#options' => $options,
        '#default_value' => $this->options['reference_display'],
        '#description' => $description,
      ];

      if (empty($this->options['reference_display'])) {
        $form['reference_display']['#description'] .= '<p>' . $this->t('Entity list will be available after saving this setting.') . '</p>';
      }

      $form['reference_arguments'] = [
        '#type' => 'textfield',
        '#size' => 50,
        '#maxlength' => 256,
        '#title' => $this->t('Arguments for the view'),
        '#default_value' => $this->options['reference_arguments'],
        '#description' => $this->t('Define arguments to send them to the selected entity reference view, they are received as contextual filter values in the same order.
        Format is arg1/arg2/...argN starting from position 1. Possible arguments types are:') . '<br>' .
        $this->t('!n - argument number n of the view dynamic URL argument %') . '<br>' .
        $this->t('#n - argument number n of the contextual filter value') . '<br>' .
        $this->t('[filter_identifier] - `Filter identifier` of the named exposed filter') . '<br>' .
        $this->t('and other strings are passed as is.'),
        '#access' => $show_reference_arguments_field,
      ];

      $this->helper->buildOptionsForm($form, $form_state);
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function valueValidate($form, FormStateInterface $form_state) {
    // We only validate if they've chosen the text field style.
    if ($this->options['type'] !== 'textfield') {
      return;
    }

    // @todo autocomplete validate
  }

  /**
   * {@inheritdoc}
   */
  protected function valueSubmit($form, FormStateInterface $form_state) {
    // Set values as NULL.
    $form_state->setValue(['options', 'value'], NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    $exposed = $this->isExposed();
    $identifier = $this->options['expose']['identifier'];

    if (!$exposed || !$identifier) {
      return;
    }

    // Except autocomplete widget.
    // We only validate if they've chosen the text field style.
    if ($this->options['type'] !== 'textfield') {
      if ($form_state->getValue($identifier) !== 'All') {
        $this->validatedExposedInput = (array) $form_state->getValue($identifier);
      }
      return;
    }

    // Autocomplete widget.
    // @todo autocomplete widget support
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    $exposed = $this->isExposed();
    $filter_is_required = $this->options['expose']['required'];

    if (!$exposed) {
      return TRUE;
    }
    // We need to know the operator, which is normally set in
    // \Drupal\views\Plugin\views\filter\FilterPluginBase::acceptExposedInput(),
    // before we actually call the parent version of ourselves.
    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id']) && isset($input[$this->options['expose']['operator_id']])) {
      $this->operator = $input[$this->options['expose']['operator_id']];
    }

    // If view is an attachment and is inheriting exposed filters, then assume
    // exposed input has already been validated.
    if (!empty($this->view->is_attachment) && $this->view->display_handler->usesExposed()) {
      $this->validatedExposedInput = (array) ($this->view->exposed_raw_input[$this->options['expose']['identifier']] ?? []);
    }

    // If we're checking for EMPTY or NOT, we don't need any input, and we can
    // say that our input conditions are met by just having the right operator.
    if ($this->operator === 'empty' || $this->operator === 'not empty') {
      return TRUE;
    }

    // If it's non-required and there's no value don't bother filtering.
    if (!$filter_is_required && empty($this->validatedExposedInput)) {
      return FALSE;
    }

    // If we have previously validated input, override values and rewrite query.
    $rewrite_query = parent::acceptExposedInput($input);
    if ($rewrite_query && isset($this->validatedExposedInput)) {
      $this->value = $this->validatedExposedInput;
    }

    return $rewrite_query;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $exposed = $this->isExposed();
    // Recalculate values if the filter is not exposed.
    if (!$exposed) {
      $options = $this->getValueOptions();
      // If there are no filter options then add zero value item to ensure
      // there are no results.
      $values = !empty($options) ? array_keys($options) : [self::NO_RESULTS_VALUE];
      $this->value = $values;
    }

    parent::query();
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $this->getValueOptions();

    return parent::adminSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    // Filter options depend on user permissions (view access on the reference
    // view), not on the individual user identity.
    $contexts[] = 'user.permissions';

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    // Adds entityreference view base entity as cache tag.
    $reference_display = $this->options['reference_display'] ?? FALSE;

    if ($reference_display && str_contains($reference_display, ':')) {
      [$view_name, $display_id] = explode(':', $reference_display, 2);
      /** @var \Drupal\views\Entity\View $config */
      $config = $this->viewStorage->load($view_name);
      if ($config instanceof View) {
        $base_table_view = $config->get('base_table');
        $definitions = $this->entityTypeManager->getDefinitions();

        // Add base entity type tags.
        $tags_by_table = $this->getListCacheTagsByTable();
        $tags = Cache::mergeTags($tags, $tags_by_table[$base_table_view] ?? []);

        // Add relationship entity type tags.
        $view_display_specific = $config->getDisplay($display_id);
        $view_display_default = $config->getDisplay('default');
        $relationships = $view_display_specific['display_options']['relationships']
          ?? $view_display_default['display_options']['relationships']
          ?? [];
        foreach ($relationships as $relationship) {
          if (isset($relationship['entity_type']) && isset($definitions[$relationship['entity_type']])) {
            $tags = Cache::mergeTags($tags, $definitions[$relationship['entity_type']]->getListCacheTags());
          }
          // Handle special case with reverse entity reference.
          if (isset($relationship['plugin_id']) && ($relationship['plugin_id'] === 'entity_reverse') && str_contains($relationship['field'], '__')) {
            [, $relationship_entity_type] = explode('__', $relationship['field']);
            if (isset($definitions[$relationship_entity_type])) {
              $tags = Cache::mergeTags($tags, $definitions[$relationship_entity_type]->getListCacheTags());
            }
          }
        }
      }
    }

    return $tags;
  }

  /**
   * Builds a map of content entity base/data table to list cache tags.
   *
   * @return array[]
   *   Keyed by table name, each value being a list of cache tags.
   */
  protected function getListCacheTagsByTable(): array {
    if ($this->tagsByTable === NULL) {
      $this->tagsByTable = [];
      foreach ($this->entityTypeManager->getDefinitions() as $definition) {
        if ($definition instanceof ContentEntityTypeInterface) {
          $table = $definition->getDataTable() ?: $definition->getBaseTable();
          if ($table) {
            $this->tagsByTable[$table] = Cache::mergeTags($this->tagsByTable[$table] ?? [], $definition->getListCacheTags());
          }
        }
      }
    }

    return $this->tagsByTable;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    // Add referenced view config as dependency.
    $reference_display = $this->options['reference_display'] ?? FALSE;

    if ($reference_display && str_contains($reference_display, ':')) {
      [$view_name] = explode(':', $reference_display, 2);
      /** @var \Drupal\views\Entity\View $config */
      $config = $this->viewStorage->load($view_name);
      if (!$config instanceof View) {
        return $dependencies;
      }
      $dependencies[$config->getConfigDependencyKey()][] = $config->getConfigDependencyName();
    }

    return $dependencies;
  }

}
