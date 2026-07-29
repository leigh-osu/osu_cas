<?php

namespace Drupal\views_date_format_sql\Plugin\views\field;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\ResultRow;
use Drupal\views_date_format_sql\Plugin\ViewsDateFormatSqlPluginDefinition;
use Drupal\views_date_format_sql\Plugin\ViewsDateFormatSqlPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A field that displays entity timestamp field data. Supports grouping.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("views_date_format_sql_field")
 */
class ViewsDateFormatSqlField extends EntityField implements ViewsDateFormatSqlPluginInterface {

  /**
   * The format.
   *
   * @var string
   */
  private $format;

  /**
   * The format string.
   *
   * @var string
   */
  private $formatString;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a \Drupal\field\Plugin\views\field\Field object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Field\FormatterPluginManager $formatter_plugin_manager
   *   The field formatter plugin manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_plugin_manager
   *   The field plugin type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    FormatterPluginManager $formatter_plugin_manager,
    FieldTypePluginManagerInterface $field_type_plugin_manager,
    LanguageManagerInterface $language_manager,
    RendererInterface $renderer,
    EntityRepositoryInterface $entity_repository,
    EntityFieldManagerInterface $entity_field_manager,
    DateFormatterInterface $date_formatter,
    ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $formatter_plugin_manager, $field_type_plugin_manager, $language_manager, $renderer, $entity_repository, $entity_field_manager, $entity_type_bundle_info);

    $this->dateFormatter = $date_formatter;
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
      $container->get('plugin.manager.field.formatter'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('language_manager'),
      $container->get('renderer'),
      $container->get('entity.repository'),
      $container->get('entity_field.manager'),
      $container->get('date.formatter'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['format_date_sql'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {

    $form['format_date_sql'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use SQL to format date'),
      '#description' => $this->t('Use the SQL database to format the date. This enables date values to be used in grouping aggregation.'),
      '#default_value' => $this->options['format_date_sql'],
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Called to add the field to a query.
   *
   * By default, all needed data is taken from entities loaded by the query
   * plugin. Columns are added only if they are used in groupings.
   */
  public function query($use_groupby = FALSE) {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    if (empty($this->options['format_date_sql'])) {
      return parent::query($use_groupby);
    }

    $fields = $this->additional_fields;
    // No need to add the entity type.
    $entity_type_key = array_search('entity_type', $fields);
    if ($entity_type_key !== FALSE) {
      unset($fields[$entity_type_key]);
    }

    if ($use_groupby) {
      // Add the fields that we're actually grouping on.
      $options = [];
      if ($this->options['group_column'] != 'entity_id') {
        $options = [$this->options['group_column'] => $this->options['group_column']];
      }
      $options += is_array($this->options['group_columns']) ? $this->options['group_columns'] : [];

      $fields = [];
      $table_mapping = $this->getTableMapping();
      $field_definition = $this->getFieldStorageDefinition();
      // Loop through and determine the actual column name from field api.
      foreach ($options as $column) {
        $fields[$column] = $table_mapping->getFieldColumnName($field_definition, $column);
      }

      $this->group_fields = $fields;
    }

    // Add additional fields (and the table join itself) if needed.
    $this->add_field_table($use_groupby);
    $this->ensureMyTable();
    $this->setDateFormat();

    // Add the field.
    $params = $this->options['group_type'] !== 'group' ? ['function' => $this->options['group_type']] : [];

    $timezone_location = $this->options['settings']['timezone'];
    if (empty($timezone_location)) {
      $timezone_location = date_default_timezone_get();
    }

    $timezone = new \DateTimeZone($timezone_location);
    $offset = $timezone->getOffset(new \DateTime());

    $field = "$this->tableAlias.$this->realField";
    $field = $query->getDateField("$this->tableAlias.$this->realField", FALSE, FALSE);

    $query->setFieldTimezoneOffset($field, $offset);

    $formula = $query->getDateFormat($field, $this->formatString, FALSE);

    $this->field_alias = $query->addField(NULL, $formula, "{$this->tableAlias}_{$this->realField}", $params);
    $query->addGroupBy($this->field_alias);

    $this->aliases[$this->definition['field_name']] = $this->field_alias;
    $this->setDateFormat();

    // Let the entity field renderer alter the query if needed.
    $this->getEntityFieldRenderer()->query($query, $this->relationship);
  }

  /**
   * Sets date format from field options.
   */
  protected function setDateFormat() {
    if (empty($this->options['settings']['date_format'])) {
      $this->format = '';
      $this->formatString = '';
      return;
    }

    $this->format = $this->options['settings']['date_format'];
    if ($this->format === 'custom') {
      $this->formatString = !empty($this->options['settings']['custom_date_format'])
          ? $this->options['settings']['custom_date_format']
          : '';
    }
    else {
      /** @var \Drupal\Core\Datetime\Entity\DateFormat $formatter */
      $formatter = DateFormat::load($this->format);
      $this->formatString = $formatter->getPattern();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    if (empty($this->options['format_date_sql'])) {
      return parent::getValue($values, $field);
    }

    $entity = $this->getEntity($values);
    // Some bundles might not have a specific field, in which case the entity
    // (potentially a fake one) doesn't have it either.
    /** @var \Drupal\Core\Field\FieldItemListInterface $field_item_list */
    $field_item_list = $entity->{$this->definition['field_name']} ?? NULL;

    if (!isset($field_item_list)) {
      // Check empty date field for "empty rows".
      if (!empty($values->is_empty_row)) {
        // Render empty date.
        if (isset($this->field_alias) && !empty($values->{$this->field_alias})) {
          return $values->{$this->field_alias};
        }
      }

      // There isn't anything we can do without a valid field.
      return NULL;
    }

    $field_item_definition = $field_item_list->getFieldDefinition();

    $values = [];
    foreach ($field_item_list as $field_item) {
      /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
      if ($field) {
        $values[] = $field_item->$field;
      }
      // Find the value using the main property of the field. If no main
      // property is provided fall back to 'value'.
      elseif ($main_property_name = $field_item->mainPropertyName()) {
        $values[] = $field_item->{$main_property_name};
      }
      else {
        $values[] = $field_item->value;
      }
    }

    if ($field_item_definition->getFieldStorageDefinition()->getCardinality() == 1) {
      $timestamp = reset($values);

      if (empty($this->format)) {
        return $timestamp;
      }

      return $this->dateFormatter->format($timestamp, $this->format, $this->formatString);
    }
    else {
      if (empty($this->format)) {
        return $values;
      }

      foreach ($values as &$value) {
        $value = $this->dateFormatter->format($value, $this->format, $this->formatString);
      }
      return $values;
    }
  }

  /**
   * Called to determine what to tell the click sorter.
   */
  public function clickSort($order) {
    if (empty($this->options['format_date_sql'])) {
      return parent::clickSort($order);
    }

    // No column selected, can't continue.
    if (empty($this->options['click_sort_column'])) {
      return NULL;
    }

    $this->ensureMyTable();
    $field_storage_definition = $this->getFieldStorageDefinition();
    $column = $this->getTableMapping()->getFieldColumnName($field_storage_definition, $this->options['click_sort_column']);
    if (!isset($this->aliases[$column])) {
      // Column is not in query; add a sort on it (without adding the column).
      $this->aliases[$column] = $this->tableAlias . '.' . $column;
    }
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addOrderBy(NULL, NULL, $order, $this->aliases[$column]);

    // Added to group ungrouped "timestamp" fields. Can occurs when
    // $this->addAdditionalFields is called with NULL $fields param.
    if ($this->view->display_handler->useGroupBy()
      && array_search($this->aliases[$column], $query->groupby, TRUE) === FALSE
    ) {
      $query->addGroupBy($this->aliases[$column]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getItems(ResultRow $values) {
    if (empty($this->options['format_date_sql'])) {
      return parent::getItems($values);
    }

    if (!$this->displayHandler->useGroupBy()) {
      $build_list = $this->getEntityFieldRenderer()->render($values, $this);
    }
    else {
      // Render date values from SQL result.
      $alias = $this->aliases[$this->definition['field_name']];
      $build_list = [
        '#items' => [$values->{$alias}], ['#markup' => $values->{$alias}],
      ];
    }

    // Code from parent function.
    if (!$build_list) {
      return [];
    }

    if ($this->options['field_api_classes']) {
      return [['rendered' => $this->renderer->render($build_list)]];
    }

    // Render using the formatted data itself.
    $items = [];
    // Each item is extracted and rendered separately, the top-level formatter
    // render array itself is never rendered, so we extract its bubbleable
    // metadata and add it to each child individually.
    $bubbleable = BubbleableMetadata::createFromRenderArray($build_list);
    foreach (Element::children($build_list) as $delta) {
      BubbleableMetadata::createFromRenderArray($build_list[$delta])
        ->merge($bubbleable)
        ->applyTo($build_list[$delta]);
      $items[$delta] = [
        'rendered' => $build_list[$delta],
        // Add the raw field items (for use in tokens).
        'raw' => $build_list['#items'][$delta],
      ];
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->dependencies = parent::calculateDependencies();

    if ($this->options['format_date_sql']) {
      $this->dependencies['module'][] = 'views_date_format_sql';
    }

    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    if (is_array($this->pluginDefinition)) {
      $this->pluginDefinition = new ViewsDateFormatSqlPluginDefinition($this->getPluginId(), self::class);
    }
    return $this->pluginDefinition;
  }

}
