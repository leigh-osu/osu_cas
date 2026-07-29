<?php

namespace Drupal\date_ical\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldFormatter\DateTimeDefaultFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Date iCal' formatter.
 */
#[FieldFormatter(
  id: 'date_ical',
  label: new TranslatableMarkup('Date iCal'),
  field_types: [
    'daterange',
    'datetime',
    'timestamp',
    'smartdate',
    'published_at',
  ],
)]
class DateIcalFormatter extends DateTimeDefaultFormatter {

  /**
   * Constructs a new DateTimeDefaultFormatter.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $date_format_storage
   *   The date format entity storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, DateFormatterInterface $date_formatter, EntityStorageInterface $date_format_storage, protected readonly EntityTypeManagerInterface $entityTypeManager, protected readonly EntityFieldManagerInterface $entityFieldManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $date_formatter, $date_format_storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('date.formatter'),
      $container->get('entity_type.manager')->getStorage('date_format'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $setting = [
      'summary_field' => '',
      'description_field' => '',
      'location_field' => '',
      'categories_field' => '',
      'organizer_field' => '',
      'status_field' => '',
      'rrule' => '',
      'rrule_field' => '',
      'url_field' => '',
      'attendee_field' => '',
      'alarm_field' => '',
      'attach_field' => '',
      'geo_field' => '',
      'disable_webcal' => TRUE,
      'download_directly' => TRUE,
    ];
    return $setting + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $textType = [
      'text',
      'text_long',
      'text_with_summary',
      'string',
      'string_long',
      'list_string',
      'entity_reference',
    ];
    $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
    $bundle = $this->fieldDefinition->getTargetBundle();
    if (empty($bundle)) {
      $field_name = $this->fieldDefinition->getName();
      $fieldStorage = $this->entityTypeManager
        ->getStorage('field_storage_config')
        ->load($entity_type . '.' . $field_name);
      $bundles = $fieldStorage->getBundles();
      $bundle = current($bundles);
    }
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $listOption = [];
    foreach ($fieldDefinitions as $field_name => $field) {
      $fieldType = $field->getType();
      $listOption[$fieldType][$field_name] = $field->getLabel();
    }
    $elements['summary_field'] = [
      '#type' => 'select',
      '#title' => $this->t('SUMMARY field'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields($textType, $listOption),
      '#default_value' => $this->getSetting('summary_field'),
      '#description' => $this->t('You may optionally change the SUMMARY component for each event in the iCal output.
        Choose which text, taxonomy term reference or Node Reference field you would like to be output as the SUMMARY.
        If using a Node Reference, the Title of the referenced node will be used.'),
    ];
    $elements['description_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Description field'),
      '#description' => $this->t("The views field to use as the body text for each event (DESCRIPTION).<br> If you wish to include another field you can leave this field blank it will render the row."),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields($textType, $listOption),
      '#default_value' => $this->getSetting('description_field'),
    ];
    $elements['location_field'] = [
      '#type' => 'select',
      '#title' => $this->t('LOCATION field'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields($textType + [
        'ad' => 'address',
        'er' => 'entity_reference',
      ], $listOption),
      '#default_value' => $this->getSetting('location_field'),
      '#description' => $this->t('You may optionally include a LOCATION component for each event in the iCal output. Choose which text or Node Reference field you would like to be output as the LOCATION. If using a Node Reference, the Title of the referenced node will be used.'),
    ];
    $elements['geo_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Geo field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field to use as the geolocation for each event (GEO).'),
      '#options' => $this->getConfigurableFields([
        'geofield',
        'geolocation',
      ], $listOption),
      '#default_value' => $this->getSetting('geo_field'),
    ];
    $elements['categories_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Categories field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field to use as the categories for each event (CATEGORIES).'),
      '#options' => $this->getConfigurableFields([
        'entity_reference',
        'list_integer',
        'list_string',
      ], $listOption),
      '#default_value' => $this->getSetting('categories_field'),
    ];
    $elements['organizer_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Organizer field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field (email or user) to use as the Organizer for each event (ORGANIZER).'),
      '#options' => $this->getConfigurableFields(['entity_reference', 'email'], $listOption),
      '#default_value' => $this->getSetting('organizer_field'),
    ];
    $elements['attendee_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Participant field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field (email or user) to use as the attendee for each event (ATTENDEE).'),
      '#options' => $this->getConfigurableFields(['entity_reference', 'email'], $listOption),
      '#default_value' => $this->getSetting('attendee_field'),
    ];
    $elements['status_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Status field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field to use as the status for each event (STATUS), It must be empty or CONFIRMED,CANCELLED,TENTATIVE. Another value will crash'),
      '#options' => $this->getConfigurableFields(['list_string', 'list_states'], $listOption),
      '#default_value' => $this->getSetting('status_field'),
    ];
    $elements['url_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Url field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field to use as the URL for each event (URL).'),
      '#options' => $this->getConfigurableFields(['link', 'entity_reference'], $listOption),
      '#default_value' => $this->getSetting('url_field'),
    ];
    $elements['rrule'] = [
      '#type' => 'select',
      '#title' => $this->t('Recurrence Rule'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) This property defines a rule for recurring events. Value allowed (SECONDLY, MINUTELY, HOURLY, DAILY, WEEKLY, MONTHLY, YEARLY)'),
      '#options' => $this->getConfigurableFields([
        'text',
        'string',
        'list_string',
      ], $listOption),
      '#default_value' => $this->getSetting('rrule'),
    ];
    $elements['rrule_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Repeating value field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) This property defines repeating pattern for recurring events.'),
      '#options' => $this->getConfigurableFields([
        'list_integer',
        'list_string',
      ], $listOption),
      '#default_value' => $this->getSetting('rrule_field'),
    ];
    $elements['alarm_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Alarm field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) If it is an integer field, it means the number of minutes that will be reported.'),
      '#options' => $this->getConfigurableFields([
        'integer',
        'list_integer',
        'duration',
        'text',
      ], $listOption),
      '#default_value' => $this->getSetting('alarm_field'),
    ];
    $elements['attach_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Attach field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field to use as the attach for each event (ATTACH).'),
      '#options' => $this->getConfigurableFields([
        'link',
        'file',
        'image',
        'entity_reference',
      ], $listOption),
      '#default_value' => $this->getSetting('attach_field'),
    ];
    $elements['disable_webcal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable webcal://'),
      '#default_value' => $this->getSetting('disable_webcal'),
      '#description' => $this->t("By default, the feed URL will use the webcal:// scheme, which allows calendar clients to easily subscribe to the feed. If you want your users to instead download this iCal feed as a file, activate this option."),
    ];
    $elements['download_directly'] = [
      '#type' => 'checkbox',
      '#parents' => ['row_options', 'download_directly'],
      '#title' => $this->t('Download directly'),
      '#description' => $this->t('Export the iCal in plain text.'),
      '#default_value' => $this->getSetting('download_directly'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    foreach ($this->getSettings() as $key => $setting) {
      $summary[$key] = ucwords(str_replace('_', ' ', $key)) . ': ' . $this->getSetting($key);
    }
    return $summary;
  }

  /**
   * Get list of fields.
   */
  protected function getConfigurableFields($type = FALSE, $listFields = []) {
    $resultField = [];
    foreach ($listFields as $field_name => $fields) {
      if (in_array($field_name, $type)) {
        $resultField += $fields;
      }
    }
    return $resultField;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $fieldType = $this->fieldDefinition->getType();
    if ($fieldType == 'daterange') {
      $separator = $this->getSetting('separator');
      $element = $this->dateTimeRangeElements($items, $separator);
    }
    elseif ($fieldType === 'smartdate') {
      $element = $this->dateTimeRangeElements($items);
    }
    else {
      $element = parent::viewElements($items, $langcode);
    }
    $view_mode = $this->viewMode;
    if (empty($element[0])) {
      return $element;
    }
    $settings = $view_mode == '_custom' ? array_filter($this->getSettings()) : [];
    if (in_array($view_mode, ['full', '_custom'])) {
      $view_mode = 'default';
    }
    $entity = $items->getEntity();
    $url = Url::fromRoute('date_ical.field', [
      'entity_type' => $this->fieldDefinition->getTargetEntityTypeId(),
      'entity_id' => $entity->id(),
      'field_name' => $items->getName(),
      'view_mode' => $view_mode,
    ], ['query' => $settings])->setAbsolute()->toString();
    if (!$this->getSetting('disable_webcal')) {
      $url = str_replace(['http://', 'https://'], 'webcal://', $url);
    }
    $element[0] = [
      '#theme' => 'date_ical_icon',
      '#url' => $url,
      '#title' => $element[0],
      '#format' => 'text/calendar',
      '#attributes' => [
        'class' => [
          'ical-icon',
        ],
        'download' => 'event.ics',
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function dateTimeRangeElements(FieldItemListInterface $items, $separator = '-') {
    $elements = [];

    foreach ($items as $delta => $item) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
      $start_date = $item->start_date ?? $item->value ? $item->get('value')->getDateTime() : NULL;
      /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
      $end_date = $item->end_date ?? $item->end_value ? $item->get('end_value')->getDateTime() : NULL;
      if ($start_date && $end_date) {
        if ($start_date->getTimestamp() !== $end_date->getTimestamp()) {
          $elements[$delta] = [
            'start_date' => $this->buildDateWithIsoAttribute($start_date),
            'separator' => ['#plain_text' => ' ' . $separator . ' '],
            'end_date' => $this->buildDateWithIsoAttribute($end_date),
          ];
        }
        else {
          $elements[$delta] = $this->buildDateWithIsoAttribute($start_date);

          if (!empty($item->_attributes)) {
            $elements[$delta]['#attributes'] += $item->_attributes;
            // Unset field item attributes since they have been included in the
            // formatter output & should not be rendered in the field template.
            unset($item->_attributes);
          }
        }
      }
    }

    return $elements;
  }

}
