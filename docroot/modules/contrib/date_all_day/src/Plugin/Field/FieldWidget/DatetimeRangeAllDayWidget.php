<?php

namespace Drupal\date_all_day\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_all_day\Utility\DateRangeAllDayHelper;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDefaultWidget;

/**
 * Plugin implementation of the 'daterange_all_day' widget.
 *
 * @FieldWidget(
 *   id = "daterange_all_day",
 *   label = @Translation("Date and time range with All day"),
 *   field_types = {
 *     "daterange",
 *     "daterange_all_day"
 *   }
 * )
 */
class DatetimeRangeAllDayWidget extends DateRangeDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Add All day checkbox with states api.
    $element['all_day'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('All day'),
      '#weight' => 2,
      '#default_value' => DateRangeAllDayHelper::isAllDay($items->get($delta)),
      '#parents' => [$items->getName(), $delta, 'all_day'],
    ];

    $element['#attached'] = [
      'library' => [
        'date_all_day/date_all_day',
      ],
    ];

    // Set end date as optional.
    $optional_end_date = $this->getFieldSetting('optional_end_date');

    $element['end_value']['#title'] = $optional_end_date ? $this->t('End date (optional)') : $this->t('End date');
    if ($element['#required'] && $optional_end_date) {
      $element['end_value']['#required'] = FALSE;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateStartEnd(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $start_date = $element['value']['#value']['object'];
    $end_date = $element['end_value']['#value']['object'];

    if ($start_date instanceof DrupalDateTime) {
      if (!$this->getFieldSetting('optional_end_date') && $end_date === NULL) {
        $form_state->setError($element['end_value'], $this->t('The @title end date is required', ['@title' => $element['#title']]));
      }

      if ($end_date instanceof DrupalDateTime) {
        if ($start_date->getTimestamp() !== $end_date->getTimestamp()) {
          $interval = $start_date->diff($end_date);
          if ($interval->invert === 1) {
            $form_state->setError($element, $this->t('The @title end date cannot be before the start date', ['@title' => $element['#title']]));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    if (!empty($values)) {

      $timezone = timezone_open(date_default_timezone_get());
    }
    foreach ($values as &$item) {

      if (!empty($item['value']) && $item['value'] instanceof DrupalDateTime) {

        $is_all_day = DateRangeAllDayHelper::isAllDay($item);

        $start_date = $item['value'];
        // All day fields start at midnight on the starting date, but are
        // stored like datetime fields, so we need to adjust the time.
        // This function is called twice, so to prevent a double conversion
        // we need to explicitly set the timezone.
        $start_date->setTimeZone($timezone);
        if ($is_all_day) {
          $start_date->setTime(0, 0, 0);
        }
        $item['value'] = $start_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, [
          'timezone' => DateTimeItemInterface::STORAGE_TIMEZONE,
        ]);

        if (!empty($item['end_value']) && $item['end_value'] instanceof DrupalDateTime) {
          $end_date = $item['end_value'];
          // All day fields end at midnight on the end date, but are
          // stored like datetime fields, so we need to adjust the time.
          // This function is called twice, so to prevent a double conversion
          // we need to explicitly set the timezone.
          $end_date->setTimeZone($timezone);
          if ($is_all_day) {
            $end_date->setTime(23, 59, 59);
          }
          $item['end_value'] = $end_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, [
            'timezone' => DateTimeItemInterface::STORAGE_TIMEZONE,
          ]);
        }
      }
    }
    return $values;
  }

}
