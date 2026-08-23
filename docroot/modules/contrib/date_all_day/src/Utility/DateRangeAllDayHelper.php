<?php

namespace Drupal\date_all_day\Utility;

use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;

/**
 * Helper class to check if a daterange item covers all day.
 *
 * @package Drupal\date_all_day\Utility
 */
class DateRangeAllDayHelper {

  const TIME_FORMAT = 'H:i:s';

  /**
   * Helper function to check if a daterange item covers all day.
   *
   * @param array|\Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem $item
   *   The date range item to check.
   *
   * @return bool
   *   A boolean indicating if a daterange item covers all day or not.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public static function isAllDay(array|DateRangeItem $item): bool {
    if ($item instanceof DateRangeItem) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
      $start_date = $item->get('value')->getDateTime();
      /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
      $end_date = $item->get('end_value')->getDateTime();
    }
    elseif (is_array($item) && isset($item['value'])) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
      $start_date = $item['value'];
      /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
      if (is_array($item['end_value']) && empty($item['end_value']['object'])) {
        $end_date = NULL;
      }
      else {
        $end_date = $item['end_value'];
      }
    }
    else {
      throw new \InvalidArgumentException('Argument $item should be either a Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem object, either an array with a \Drupal\Core\Datetime\DrupalDateTime in the "value" key.');
    }

    $timezone = date_default_timezone_get();
    return !empty($start_date) && $start_date->format(self::TIME_FORMAT, ['timezone' => $timezone]) === '00:00:00' && (empty($end_date) || $end_date->format(self::TIME_FORMAT, ['timezone' => $timezone]) === '23:59:59');
  }

}
