<?php

namespace Drupal\date_ical\Plugin\views\row;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\views\Attribute\ViewsRow;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Row handler plugin for displaying Date iCal.
 *
 * @ingroup views_row_plugins
 */
#[ViewsRow(
  id: "date_ical",
  title: new TranslatableMarkup("iCal Fields"),
  help: new TranslatableMarkup("Mapping iCal vs fields."),
  display_types: ["feed"],
)]
class DateICal extends RowPluginBase {

  /**
   * Constructs a DateICal instance.
   *
   * @param array $configuration
   *   The configuration for the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityFieldManagerInterface $entityFieldManager, protected RequestStack $requestStack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['date_field'] = ['default' => ''];
    $options['end_field'] = ['default' => ''];
    $options['summary_field'] = ['default' => ''];
    $options['description_field'] = ['default' => ''];
    $options['location_field'] = ['default' => ''];
    $options['categories_field'] = ['default' => ''];
    $options['organizer_field'] = ['default' => ''];
    $options['status_field'] = ['default' => ''];
    $options['rrule'] = ['default' => ''];
    $options['rrule_field'] = ['default' => ''];
    $options['rrule_raw_field'] = ['default' => ''];
    $options['url_field'] = ['default' => ''];
    $options['attendee_field'] = ['default' => ''];
    $options['alarm_field'] = ['default' => ''];
    $options['attach_field'] = ['default' => ''];
    $options['geo_field'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function usesFields() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $listOption = $this->getFieldType();
    $textType = [
      'text',
      'text_long',
      'text_with_summary',
      'string',
      'string_long',
      'list_string',
      'entity_reference',
      'unknown',
    ];
    $dateType = [
      'daterange',
      'datetime',
      'timestamp',
      'smartdate',
      'changed',
      'created',
      'date_recur',
    ];
    $form['date_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Date field'),
      '#options' => $this->getConfigurableFields($dateType, $listOption),
      '#default_value' => $this->options['date_field'],
      '#description' => $this->t('Please identify the field to use as the iCal date for each item in this view.
          Add a Date Filter or a Date Argument to the view to limit results to content in a specified date range.'),
      '#required' => TRUE,
    ];
    $form['end_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Date end field'),
      '#options' => $this->getConfigurableFields(['datetime', 'timestamp'], $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['end_field'],
      '#description' => $this->t('Use this field if you have a separate date.'),
    ];
    $form['summary_field'] = [
      '#type' => 'select',
      '#title' => $this->t('SUMMARY field'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields($textType, $listOption),
      '#default_value' => $this->options['summary_field'],
      '#description' => $this->t('You may optionally change the SUMMARY component for each event in the iCal output.
        Choose which text, taxonomy term reference or Node Reference field you would like to be output as the SUMMARY.
        If using a Node Reference, the Title of the referenced node will be used.'),
    ];
    $form['description_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Description field'),
      '#description' => $this->t("The views field to use as the body text for each event (DESCRIPTION).<br>
          If you wish to include another field you can leave this field blank it will render the row."),
      '#options' => $this->getConfigurableFields($textType, $listOption),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['description_field'],
    ];
    $form['location_field'] = [
      '#type' => 'select',
      '#title' => $this->t('LOCATION field'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->getConfigurableFields($textType + [
        'ad' => 'address',
        'link' => 'link',
        'ref' => 'entity_reference',
      ], $listOption),
      '#default_value' => $this->options['location_field'],
      '#description' => $this->t('You may optionally include a LOCATION component for each event in the iCal output.
        Choose which text or Node Reference field you would like to be output as the LOCATION.
        If using a Node Reference, the Title of the referenced node will be used.'),
    ];
    $form['geo_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Geo field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field to use as the geolocation for each event (GEO).'),
      '#options' => $this->getConfigurableFields([
        'geofield',
        'geolocation',
      ], $listOption),
      '#default_value' => $this->options['geo_field'],
    ];
    $form['categories_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Categories field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field to use as the categories for each event (CATEGORIES).'),
      '#options' => $this->getConfigurableFields([
        'entity_reference',
        'list_integer',
        'list_string',
      ], $listOption),
      '#default_value' => $this->options['categories_field'],
    ];
    $form['organizer_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Organizer field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field (email or user) to use as the Organizer for each event (ORGANIZER).'),
      '#options' => $this->getConfigurableFields(['entity_reference', 'email', 'unknown'], $listOption),
      '#default_value' => $this->options['organizer_field'],
    ];
    $form['attendee_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Participant field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field (email or user) to use as the attendee for each event (ATTENDEE).'),
      '#options' => $this->getConfigurableFields(['entity_reference', 'email'], $listOption),
      '#default_value' => $this->options['attendee_field'],
    ];
    $form['status_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Status field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field to use as the status for each event (STATUS), It must be empty or CONFIRMED,CANCELLED,TENTATIVE. Another value will crash'),
      '#options' => $this->getConfigurableFields(['list_string', 'list_states'], $listOption),
      '#default_value' => $this->options['status_field'],
    ];
    $form['rrule'] = [
      '#type' => 'select',
      '#title' => $this->t('Recurrence frequency (RRULE FREQ)'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) How often the event repeats.'),
      '#options' => [
        'SECONDLY' => $this->t('Secondly'),
        'MINUTELY' => $this->t('Minutely'),
        'HOURLY' => $this->t('Hourly'),
        'DAILY' => $this->t('Daily'),
        'WEEKLY' => $this->t('Weekly'),
        'MONTHLY' => $this->t('Monthly'),
        'YEARLY' => $this->t('Yearly'),
      ],
      '#default_value' => $this->options['rrule'],
    ];
    $form['rrule_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Repeat count field (RRULE COUNT)'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) Field providing total occurrences (numeric). Requires Frequency.'),
      '#options' => $this->getConfigurableFields([
        'list_integer',
        'list_string',
        'unknown',
      ], $listOption),
      '#default_value' => $this->options['rrule_field'],
      '#states' => [
        'visible' => [
          ':input[name="row_options[rrule]"]' => ['filled' => TRUE],
        ],
      ],
    ];
    $form['rrule_raw_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Raw RRULE field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) Full RRULE string for complex recurrence.'),
      '#options' => $this->getConfigurableFields([
        'list_string',
        'string',
        'text',
        'unknown',
      ], $listOption),
      '#default_value' => $this->options['rrule_raw_field'],
    ];
    $form['url_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Url field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) The views field to use as the URL for each event (URL).'),
      '#options' => $this->getConfigurableFields(['url', 'link', 'entity_reference'], $listOption),
      '#default_value' => $this->options['url_field'],
    ];
    $form['alarm_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Alarm field'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('(optional) If it is an integer field, it means the number of minutes that will be reported.'),
      '#options' => $this->getConfigurableFields([
        'integer',
        'list_integer',
        'duration',
        'text',
        'unknown',
      ], $listOption),
      '#default_value' => $this->options['alarm_field'],
    ];
    $form['attach_field'] = [
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
      '#default_value' => $this->options['attach_field'],
    ];

  }

  /**
   * Get field type.
   */
  protected function getFieldType() {
    $listOption = [];
    $entity_type = $this->view->getBaseEntityType()->id();
    $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
    $fields = $this->displayHandler->getHandlers('field');
    $labels = $this->displayHandler->getFieldLabels();
    foreach ($fields as $field_name => $field) {
      $field_definition = $field_definitions[$field_name] ?? $field->defineOptions();
      $field_type_links = 'unknown';
      if (is_array($field_definition)) {
        $field_type_links = $field_definition["type"]["default"] ?? 'unknown';
      }
      elseif (is_object($field_definition)) {
        $field_type_links = $field_definition->getType() ?? $field_definition["type"]["default"];
        if ($field_definition instanceof BaseFieldDefinition && in_array($field_name, ['title', 'name', 'label'])) {
          $listOption['url'][$field_name] = $labels[$field_name];
        }
      }

      // Treat core 'Link to Content' as URL-capable for the URL field selector.
      if ($field instanceof PluginInspectionInterface && $field->getPluginId() === 'entity_link') {
        $listOption['url'][$field_name] = $labels[$field_name];
      }

      $listOption[$field_type_links][$field_name] = $labels[$field_name];
    }
    return $listOption;
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
   * Get event record.
   *
   * {@inheritDoc}
   */
  public function render($row) {
    $entity = $row->_entity ?? NULL;
    if (empty($entity) && !empty($row->_object) && method_exists($row->_object, 'getValue')) {
      $entity = $row->_object->getValue();
    }
    if (empty($entity)) {
      return FALSE;
    }
    $style = $this->view->getStyle();
    $listOption = $this->getFieldType();
    $iCal = [];
    global $base_url;
    foreach ($this->options as $key => $fieldMapping) {
      if (!empty($fieldMapping) && is_string($fieldMapping)) {
        $iCal[$key] = $style->getFieldValue($row->index, $fieldMapping);
        $render = $style->getField($row->index, $fieldMapping);
        if (is_object($render) && method_exists($render, 'jsonSerialize')) {
          $iCal[$key] = trim(html_entity_decode($render->jsonSerialize()));
        }
        if (is_array($iCal[$key]) && !empty($iCal[$key]['value'])) {
          $iCal[$key] = $iCal[$key]['value'];
        }
        if (empty($iCal[$key])) {
          continue;
        }
        elseif (is_string($iCal[$key])) {
          $iCal[$key] = html_entity_decode($iCal[$key]);
        }
        if ($entity->hasField('created') && !empty($entity->get('created'))) {
          $date = DrupalDateTime::createFromTimestamp($entity->get('created')->value, 'UTC');
          $iCal['created'] = $date->format("Y-m-d\TH:i:s");
        }
        if ($entity->hasField('changed') && !empty($entity->get('changed'))) {
          $date = DrupalDateTime::createFromTimestamp($entity->get('changed')->value, 'UTC');
          $iCal['last-modified'] = $date->format("Y-m-d\TH:i:s");
        }
        $type = '';
        $entityTitle = '';
        $fieldValue = $entity->hasField($fieldMapping) ? $entity->get($fieldMapping) : NULL;
        if (isset($listOption["entity_reference"][$fieldMapping]) && !empty($fieldValue)) {
          $entityRef = $fieldValue->referencedEntities();
          if (!empty($entityRef)) {
            $entityRef = current($entityRef);
            $type = $entityRef->getEntityTypeId();
          }
          $ns = ['label', 'getTitle', 'getDisplayName', 'getName', 'getLabel'];
          if (is_object($entityRef)) {
            foreach ($ns as $name) {
              if (method_exists($entityRef, $name)) {
                $entityTitle = $entityRef->$name();
                break;
              }
            }
          }
        }
        switch ($key) {
          case 'date_field':
          case 'end_field':
            if ($fieldValue) {
              $iCal[$key] = current($fieldValue->getValue());
              // @todo Get correct delta for recurring smartdates.
              $type = $fieldValue->getfieldDefinition()->getType();
              if ($type === 'smartdate') {
                $values = $fieldValue->getValue();
                $entity_type = $fieldValue->getfieldDefinition()->getTargetEntityTypeId();
                $table = $entity_type . '__' . $fieldMapping . '_delta';
                $delta = $row->$table ?? NULL;
                if (!empty($delta) && !empty($values[$delta])) {
                  $iCal[$key] = $values[$delta];
                }
              }
            }
            break;

          case 'geo_field':
            if ($fieldValue) {
              $iCal[$key] = current($fieldValue->getValue());
            }
            elseif (!empty($iCal[$key]) && is_string($iCal[$key])) {
              [$longitude, $latitude] = array_filter(explode(' ', trim(preg_replace('/[^0-9. ]/', '', strip_tags($iCal[$key])))));
              $iCal[$key] = [
                'lat' => $latitude,
                'lon' => $longitude,
              ];
            }
            break;

          case 'location_field':
            $address = current($fieldValue?->getValue() ?? [FALSE]);
            if (is_array($address)) {
              // Check is reference.
              if (!empty($entityRef)) {
                $address = [
                  'title' => $entityTitle ?? '',
                  'uri' => 'internal:' . $entityRef->toUrl()->toString(),
                ];
              }
              if (!empty($address['uri'])) {
                $iCal[$key] = $address;
                $parsedUrl = parse_url($address['uri']);
                $url = $address['uri'];
                if ($parsedUrl['scheme'] == 'internal') {
                  $url = Url::fromUri($address['uri'], ['absolute' => TRUE])
                    ->toString();
                }
                $iCal[$key]['url'] = $url;
              }
            }
            break;

          case 'summary_field':
            if (!empty($entityTitle)) {
              $iCal[$key] = $entityTitle;
            }
            break;

          case 'organizer_field':
          case 'attendee_field':
            if ($type == 'user') {
              $iCal[$key] = [];
              if ($fieldValue) {
                foreach ($fieldValue->referencedEntities() as $entityRef) {
                  $iCal[$key][] = [
                    'name' => $entityRef->getDisplayName(),
                    'mail' => $entityRef->getEmail(),
                  ];
                }
              }
            }
            else {
              $invites = explode(', ', $fieldValue?->getString() ?? []);
              $iCal[$key] = [];
              foreach ($invites as $index => $invite) {
                preg_match_all('/([\w+\.]*\w+@[\w+\.]*\w+[\w+\-\w+]*\.\w+)/is', trim($invite), $matches);
                $mail = current($matches[0]);
                $iCal[$key][$index]['mail'] = $mail;
                $find = [$mail, '<', '>', '.'];
                $name = trim(ucwords(str_replace($find, ' ', $invite)));
                if (empty($name)) {
                  $ext = explode('@', $invite);
                  $name = trim(ucwords(str_replace($find, ' ', $ext[0])));
                }
                $iCal[$key][$index]['name'] = trim($name);
              }
            }
            break;

          case 'url_field':
            if (!empty($iCal[$key])) {
              $parsedUrl = parse_url($iCal[$key]);
              if (!empty($parsedUrl['scheme'])) {
                if ($parsedUrl['scheme'] == 'internal') {
                  $iCal[$key] = Url::fromUri($iCal[$key], ['absolute' => TRUE])->toString();
                  $parsedUrl = parse_url($iCal[$key]);
                }
                if (!in_array($parsedUrl['scheme'], ['https', 'http'])) {
                  $iCal[$key] = FALSE;
                }
              }
              $matches = [];
              if (!empty($iCal[$key]) && preg_match('/href="([^"]*)"/i', $render->__toString(), $matches)) {
                $relative_url = $matches[1] ?? NULL;
                if ($relative_url) {
                  if (str_starts_with($iCal[$key] = $relative_url, '/')) {
                    $iCal[$key] = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() . $relative_url;
                  }
                }
                else {
                  $iCal[$key] = $entity->toUrl()->setAbsolute()->toString();
                }
              }
            }
            break;

          case 'attach_field':
            if (!empty($iCal[$key]) && is_object($fieldValue) && method_exists($fieldValue, 'referencedEntities')) {
              $entityRef = $fieldValue->referencedEntities();
              if (!empty($entityRef)) {
                $iCal[$key] = [];
                foreach ($entityRef as $attachEntity) {
                  if ($attachEntity instanceof File) {
                    $file_url = $attachEntity->createFileUrl(FALSE);
                    $iCal[$key][] = [
                      'url' => $file_url,
                      'FILENAME' => $attachEntity->getFilename(),
                      'FMTTYPE' => $attachEntity->getMimeType(),
                    ];
                  }
                  elseif ($attachEntity instanceof EntityBase) {
                    $iCal[$key][] = [
                      'url' => $attachEntity->toUrl('canonical', ['absolute' => TRUE])->toString(),
                      'FILENAME' => $attachEntity->label(),
                    ];
                  }
                }
              }
            }
            elseif (!empty($listOption["link"][$fieldMapping])) {
              $viewField = $this->view->field[$fieldMapping];
              $attaches = $viewField->getItems($row);
              $iCal[$key] = [];
              foreach ($attaches as $index => $attache) {
                $valAttach = $attache['raw']->getValue();
                if (!empty($valAttach['title'])) {
                  $iCal[$key][$index]['url'] = Url::fromUri($valAttach['uri'], ['absolute' => TRUE])->toString();
                  $iCal[$key][$index]['FILENAME'] = $valAttach['title'];
                }
                else {
                  $iCal[$key][$index] = Url::fromUri($valAttach['uri'], ['absolute' => TRUE])->toString();
                }
              }
            }
            break;
        }
        // Add absolute url to link.
        if (is_string($iCal[$key]) && $key !== 'rrule_raw_field') {
          $iCal[$key] = preg_replace('/\s+/', ' ', trim($this->relToAbs($iCal[$key], $base_url)));
          $iCal[$key] = preg_replace('/\R+/u', ' ', $iCal[$key]);
        }
      }
    }
    return $iCal;
  }

  /**
   * Search and replace relative URL to absolute URL.
   *
   * @param string $text
   *   The HTML text.
   * @param string $base
   *   The base URL.
   *
   * @return string|null
   *   The modified HTML text.
   */
  protected function relToAbs($text, $base) {
    // Replace links.
    $pattern = '/<a([^>]*) href=["\']([^http|ftp|https|mailto][^"\']*)["\']/i';
    $replace = '<a${1} href="' . $base . '${2}"';
    $text = preg_replace($pattern, $replace, $text);

    // Replace images.
    $pattern = '/<img([^>]*) src=["\']([^http|ftp|https][^"\']*)["\']/i';
    $replace = '<img${1} src="' . $base . '${2}"';
    return preg_replace($pattern, $replace, $text);
  }

}
