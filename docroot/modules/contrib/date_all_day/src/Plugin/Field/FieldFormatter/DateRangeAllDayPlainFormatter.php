<?php

namespace Drupal\date_all_day\Plugin\Field\FieldFormatter;

use Drupal\datetime_range\Plugin\Field\FieldFormatter\DateRangePlainFormatter;

/**
 * Plugin implementation of the 'Plain' formatter for 'daterange' fields.
 *
 * This formatter renders the data range as a plain text string, with a
 * configurable separator using an ISO-like date format string.
 *
 * @FieldFormatter(
 *   id = "daterange_all_day_plain",
 *   label = @Translation("Plain (All day) (DEPRECATED)"),
 *   field_types = {
 *     "daterange"
 *   }
 * )
 */
class DateRangeAllDayPlainFormatter extends DateRangePlainFormatter {

  use DateRangeAllDayTrait;

}
