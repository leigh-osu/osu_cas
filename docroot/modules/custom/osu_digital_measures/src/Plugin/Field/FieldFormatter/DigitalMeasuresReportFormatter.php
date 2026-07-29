<?php

namespace Drupal\osu_digital_measures\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\externalauth\AuthmapInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays a Digital Measures report for the profile owner's ONID account.
 *
 * Port of the Drupal 7 'osu_digital_measures_report' field formatter. When the
 * boolean field is on, a container is rendered and the Digital Measures
 * web-profiles widget loads the configured report client-side, keyed on the
 * CAS (ONID) authname of the entity owner.
 */
#[FieldFormatter(
  id: "osu_digital_measures_report",
  label: new TranslatableMarkup("Digital Measures report"),
  field_types: [
    "boolean",
  ],
)]
class DigitalMeasuresReportFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The authentication provider whose authname is the ONID username.
   */
  const AUTH_PROVIDER = 'cas';

  /**
   * The external authentication map.
   *
   * @var \Drupal\externalauth\AuthmapInterface
   */
  protected AuthmapInterface $authmap;

  /**
   * Constructs a DigitalMeasuresReportFormatter.
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
   *   Any third party settings.
   * @param \Drupal\externalauth\AuthmapInterface $authmap
   *   The external authentication map.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    AuthmapInterface $authmap,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->authmap = $authmap;
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
      $container->get('externalauth.authmap')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'dm_client_id' => '',
      'dm_report_id' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['dm_client_id'] = [
      '#title' => $this->t('DM Client ID'),
      '#type' => 'textfield',
      '#size' => 50,
      '#default_value' => $this->getSetting('dm_client_id'),
      '#required' => TRUE,
    ];
    $element['dm_report_id'] = [
      '#title' => $this->t('DM Report ID'),
      '#type' => 'textfield',
      '#size' => 50,
      '#default_value' => $this->getSetting('dm_report_id'),
      '#required' => TRUE,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Show DM report @dm_report_id from client @dm_client_id', [
      '@dm_report_id' => $this->getSetting('dm_report_id'),
      '@dm_client_id' => $this->getSetting('dm_client_id'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $client_id = $this->getSetting('dm_client_id');
    $report_id = $this->getSetting('dm_report_id');
    if ($client_id === '' || $report_id === '') {
      return $elements;
    }

    // Resolve the ONID (CAS authname) of the entity owner.
    $entity = $items->getEntity();
    $onid = $this->getOwnerOnid($entity);
    if (empty($onid)) {
      return $elements;
    }

    $field_name = $items->getFieldDefinition()->getName();

    foreach ($items as $delta => $item) {
      // Boolean field: only render the report when it is switched on.
      if (empty($item->value)) {
        continue;
      }

      $container_id = sprintf('dm-report-%s-%s-%d', str_replace('_', '-', $field_name), $entity->id(), $delta);

      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['id' => $container_id],
        '#attached' => [
          'library' => ['osu_digital_measures/report'],
          'drupalSettings' => [
            'osuDigitalMeasures' => [
              'reports' => [
                $container_id => [
                  'container' => '#' . $container_id,
                  'clientId' => $client_id,
                  'reportId' => $report_id,
                  'username' => $onid,
                ],
              ],
            ],
          ],
        ],
      ];
    }

    return $elements;
  }

  /**
   * Returns the ONID (CAS authname) of the entity owner, if any.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the field is attached to (e.g. a profile node).
   *
   * @return string|null
   *   The ONID username, or NULL if the owner has no CAS authname.
   */
  protected function getOwnerOnid($entity): ?string {
    if (!$entity instanceof EntityOwnerInterface) {
      return NULL;
    }
    $owner = $entity->getOwner();
    if (!$owner || $owner->isAnonymous()) {
      return NULL;
    }
    $authname = $this->authmap->get((int) $owner->id(), self::AUTH_PROVIDER);
    return $authname ?: NULL;
  }

}
