<?php

declare(strict_types=1);

namespace Drupal\date_ap_style\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime_range\DateTimeRangeTrait;

/**
 * Plugin implementation of the AP style date range field formatter.
 */
#[FieldFormatter(
  id: 'daterange_ap_style',
  label: new TranslatableMarkup('AP Style'),
  field_types: [
    'daterange',
    'smartdate',
  ]
)]
class ApStyleDateRangeFieldFormatter extends ApSelectFormatterBase {

  // Alias core DateTimeRangeTrait methods so we can wrap them and trigger
  // deprecation warnings on use without changing behavior.
  use DateTimeRangeTrait {
    DateTimeRangeTrait::dateTimeRangeDefaultSettings as private trait_dateTimeRangeDefaultSettings;
    DateTimeRangeTrait::dateTimeRangeSettingsForm as private trait_dateTimeRangeSettingsForm;
    DateTimeRangeTrait::dateTimeRangeSettingsSummary as private trait_dateTimeRangeSettingsSummary;
    DateTimeRangeTrait::getFromToOptions as private trait_getFromToOptions;
    DateTimeRangeTrait::startDateIsDisplayed as private trait_startDateIsDisplayed;
    DateTimeRangeTrait::endDateIsDisplayed as private trait_endDateIsDisplayed;
    DateTimeRangeTrait::renderStartEnd as private trait_renderStartEnd;
    DateTimeRangeTrait::renderStartEndWithIsoAttribute as private trait_renderStartEndWithIsoAttribute;
    DateTimeRangeTrait::viewElements as private trait_viewElements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $config = \Drupal::config('date_ap_style.settings');
    return [
      'separator' => $config->get('separator') ?? 'to',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['separator'] = [
      '#type' => 'select',
      '#title' => $this->t('Date range separator'),
      '#options' => [
        'to' => $this->t('to'),
        'hyphen' => $this->t('Hyphen'),
        'endash' => $this->t('En dash'),
      ],
      '#default_value' => $this->getSetting('separator'),
    ];
    $elements['month_only']['#description'] = $this->t('Shows only the month (e.g., Aug.) for the date, excluding the day. Year shown as required.');

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    if ($this->getSetting('separator') === 'endash') {
      $summary[] = $this->t('Using en dash date range separator');
    }
    if ($this->getSetting('separator') === 'hyphen') {
      $summary[] = $this->t('Using hyphen date range separator');
    }
    else {
      $summary[] = $this->t('Using "to" date range separator');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    return $this->buildViewElements($items, $langcode);
  }

  /**
   * {@inheritdoc}
   *
   * Extends parent to add separator option for date ranges.
   */
  protected function buildViewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    $opts = [
      'always_display_year',
      'display_day',
      'use_today',
      'cap_today',
      'display_time',
      'hide_date',
      'time_before_date',
      'use_all_day',
      'month_only',
      'display_noon_and_midnight',
      'capitalize_noon_and_midnight',
    ];

    $options = [];

    foreach ($opts as $opt) {
      if ($this->getSetting($opt)) {
        // Cast to boolean to handle form values that come through as 0/1.
        $options[$opt] = (bool) $this->getSetting($opt);
      }
    }

    // Add the separator option for date ranges (not in parent).
    $options['separator'] = $this->getSetting('separator');

    $timezone = $this->getSetting('timezone') ?: NULL;
    $field_type = $items->getFieldDefinition()->getType();

    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->processItem($item, $options, $timezone, $langcode, $field_type);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function processItem($item, $options, $timezone, $langcode, $field_type): array {
    $dates = [];

    if (!empty($item->start_date) && !empty($item->end_date)) {
      $start_date = $item->start_date;
      $end_date = $item->end_date;
      $dates['start'] = (int) $start_date->getTimestamp();
      $dates['end'] = (int) $end_date->getTimestamp();
    }

    if ($field_type === 'smartdate') {
      if (!empty($item->value) && !empty($item->end_value)) {
        $dates['start'] = (int) $item->value;
        $dates['end'] = (int) $item->end_value;
      }
    }

    if (isset($dates['start']) && isset($dates['end'])) {
      return [
        '#cache' => [
          'contexts' => [
            'timezone',
          ],
        ],
        '#markup' => $this->apStyleDateFormatter->formatRange($dates, $options, $timezone, $langcode, $field_type),
      ];
    }

    return [];
  }

  /**
   * Wrapper around DateTimeRangeTrait::dateTimeRangeDefaultSettings().
   *
   * Triggers a deprecation warning when the trait method is used.
   *
   * @deprecated in date_ap_style:2.1.0 and is removed from date_ap_style:3.0.0.
   *   The formatter now handles date ranges internally without requiring the
   *   datetime_range module.
   * @see https://www.drupal.org/project/date_ap_style/issues/3543606
   */
  protected static function dateTimeRangeDefaultSettings(): array {
    @trigger_error('Calling ' . __METHOD__ . '() is deprecated in date_ap_style:2.1.0 and is removed from date_ap_style:3.0.0. The formatter now handles date ranges internally without requiring the datetime_range module. See https://www.drupal.org/project/date_ap_style/issues/3543606', E_USER_DEPRECATED);
    return self::trait_dateTimeRangeDefaultSettings();
  }

  /**
   * Wrapper around DateTimeRangeTrait::dateTimeRangeSettingsForm().
   *
   * @deprecated in date_ap_style:2.1.0 and is removed from date_ap_style:3.0.0.
   *   The formatter now handles date ranges internally without requiring the
   *   datetime_range module.
   * @see https://www.drupal.org/project/date_ap_style/issues/3543606
   */
  protected function dateTimeRangeSettingsForm(array $form): array {
    $this->triggerRangeTraitDeprecationWarning();
    return $this->trait_dateTimeRangeSettingsForm($form);
  }

  /**
   * Wrapper around DateTimeRangeTrait::dateTimeRangeSettingsSummary().
   *
   * @deprecated in date_ap_style:2.1.0 and is removed from date_ap_style:3.0.0.
   *   The formatter now handles date ranges internally without requiring the
   *   datetime_range module.
   * @see https://www.drupal.org/project/date_ap_style/issues/3543606
   */
  protected function dateTimeRangeSettingsSummary(): array {
    $this->triggerRangeTraitDeprecationWarning();
    return $this->trait_dateTimeRangeSettingsSummary();
  }

  /**
   * Wrapper around DateTimeRangeTrait::getFromToOptions().
   *
   * @deprecated in date_ap_style:2.1.0 and is removed from date_ap_style:3.0.0.
   *   The formatter now handles date ranges internally without requiring the
   *   datetime_range module.
   * @see https://www.drupal.org/project/date_ap_style/issues/3543606
   */
  protected function getFromToOptions(): array {
    $this->triggerRangeTraitDeprecationWarning();
    return $this->trait_getFromToOptions();
  }

  /**
   * Wrapper around DateTimeRangeTrait::startDateIsDisplayed().
   *
   * @deprecated in date_ap_style:2.1.0 and is removed from date_ap_style:3.0.0.
   *   The formatter now handles date ranges internally without requiring the
   *   datetime_range module.
   * @see https://www.drupal.org/project/date_ap_style/issues/3543606
   */
  protected function startDateIsDisplayed(): bool {
    $this->triggerRangeTraitDeprecationWarning();
    return $this->trait_startDateIsDisplayed();
  }

  /**
   * Wrapper around DateTimeRangeTrait::endDateIsDisplayed().
   *
   * @deprecated in date_ap_style:2.1.0 and is removed from date_ap_style:3.0.0.
   *   The formatter now handles date ranges internally without requiring the
   *   datetime_range module.
   * @see https://www.drupal.org/project/date_ap_style/issues/3543606
   */
  protected function endDateIsDisplayed(): bool {
    $this->triggerRangeTraitDeprecationWarning();
    return $this->trait_endDateIsDisplayed();
  }

  /**
   * Wrapper around DateTimeRangeTrait::renderStartEnd().
   *
   * @deprecated in date_ap_style:2.1.0 and is removed from date_ap_style:3.0.0.
   *   The formatter now handles date ranges internally without requiring the
   *   datetime_range module.
   * @see https://www.drupal.org/project/date_ap_style/issues/3543606
   */
  protected function renderStartEnd(DrupalDateTime $start_date, string $separator, DrupalDateTime $end_date): array {
    $this->triggerRangeTraitDeprecationWarning();
    return $this->trait_renderStartEnd($start_date, $separator, $end_date);
  }

  /**
   * Wrapper around DateTimeRangeTrait::renderStartEndWithIsoAttribute().
   *
   * @deprecated in date_ap_style:2.1.0 and is removed from date_ap_style:3.0.0.
   *   The formatter now handles date ranges internally without requiring the
   *   datetime_range module.
   * @see https://www.drupal.org/project/date_ap_style/issues/3543606
   */
  protected function renderStartEndWithIsoAttribute(DrupalDateTime $start_date, string $separator, DrupalDateTime $end_date): array {
    $this->triggerRangeTraitDeprecationWarning();
    return $this->trait_renderStartEndWithIsoAttribute($start_date, $separator, $end_date);
  }

  /**
   * Wrapper around DateTimeRangeTrait::viewElements().
   *
   * Note: This class already overrides viewElements() to delegate to
   * parent::buildViewElements(). The following wrapper exists only to
   * ensure a deprecation is emitted if a child class calls
   * parent::viewElements() on this class.
   *
   * @deprecated in date_ap_style:2.1.0 and is removed from date_ap_style:3.0.0.
   *   The formatter now handles date ranges internally without requiring the
   *   datetime_range module.
   * @see https://www.drupal.org/project/date_ap_style/issues/3543606
   */
  protected function traitWrappedViewElements(FieldItemListInterface $items, $langcode): array {
    // This method is intentionally not part of the public API; it exists only
    // to provide a deprecation path for trait usage if explicitly invoked by a
    // child class via this protected wrapper.
    $this->triggerRangeTraitDeprecationWarning();
    return $this->trait_viewElements($items, $langcode);
  }

  /**
   * Trigger deprecation warning for usage of DateTimeRangeTrait.
   */
  protected function triggerRangeTraitDeprecationWarning(): void {
    // Deprecation notice for DateTimeRangeTrait dependency.
    // The trait was used in previous versions but is no longer needed as
    // date range formatting is now handled internally.
    @trigger_error('The DateTimeRangeTrait dependency in ' . __CLASS__ . ' is deprecated in date_ap_style:2.1.0 and is removed from date_ap_style:3.0.0. The formatter now handles date ranges internally without requiring the datetime_range module. See https://www.drupal.org/project/date_ap_style/issues/3543606', E_USER_DEPRECATED);
  }

}
