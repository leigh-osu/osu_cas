<?php

/**
 * @file
 * views_aggregator_functions.inc
 *
 * The complete set of views aggregation functions that come with the module.
 * Note that other modules can add their own functions by implementing
 * hook_views_aggregation_functions_info()
 */

use Drupal\views\Plugin\views\field\FieldHandlerInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Implements hook_views_aggregation_functions_info().
 */
function views_aggregator_views_aggregation_functions_info(): array {
  $functions = [
    // The first two are special and must be executed before the others.
    'views_aggregator_row_filter' => [
      'group' => t('Filter rows (by regexp) *'),
      'column' => NULL,
    ],
    'views_aggregator_group_and_compress' => [
      'group' => t('Group and compress'),
      'column' => NULL,
    ],

    /* Regular aggregation functions start here, in alphabetical order */

    'views_aggregator_average' => [
      'group' => t('Average *'),
      'column' => t('Average *'),
    ],
    'views_aggregator_count' => [
      'group' => t('Count (having regexp) *'),
      'column' => t('Count (having regexp) *'),
      'is_renderable' => FALSE,
    ],
    'views_aggregator_count_unique' => [
      'group' => t('Count unique (having regexp) *'),
      'column' => t('Count unique (having regexp) *'),
      'is_renderable' => FALSE,
    ],
    'views_aggregator_first' => [
      'group' => t('Display first member'),
      'column' => NULL,
    ],
    'views_aggregator_enumerate_raw' => [
      'group' => t('Enumerate *'),
      'column' => t('Enumerate *'),
      'is_renderable' => FALSE,
    ],
    'views_aggregator_enumerate' => [
      'group' => t('Enumerate (sort, no dupl.) *'),
      'column' => t('Enumerate (sort, no dupl.) *'),
      'is_renderable' => FALSE,
    ],
    'views_aggregator_replace' => [
      'group' => t('Label (enter below) *'),
      'column' => t('Label (enter below) *'),
      'is_renderable' => FALSE,
    ],
    'views_aggregator_maximum' => [
      'group' => t('Maximum'),
      'column' => t('Maximum'),
    ],
    'views_aggregator_median' => [
      'group' => t('Median'),
      'column' => t('Median'),
    ],
    'views_aggregator_minimum' => [
      'group' => t('Minimum'),
      'column' => t('Minimum'),
    ],
    'views_aggregator_range' => [
      'group' => t('Range *'),
      'column' => t('Range *'),
    ],
    'views_aggregator_sum' => [
      'group' => t('Sum'),
      'column' => t('Sum'),
    ],
    'views_aggregator_tally' => [
      'group' => t('Tally members *'),
      'column' => t('Tally members *'),
      'is_renderable' => FALSE,
    ],
  ];
  return $functions;
}

/**
 * Keeps only the groups that match the regular expression filter.
 *
 * Matching takes place on the raw values (1000, rather than "$ 1,000").
 *
 * @param \Drupal\views\Plugin\views\style\StylePluginBase $views_plugin_style
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to count groups members in.
 * @param string|null $regexp
 *   (optional) If empty all result rows are kept.
 */
function views_aggregator_row_filter(StylePluginBase $views_plugin_style, FieldHandlerInterface $field_handler, ?string $regexp = NULL) {
  if (empty($regexp)) {
    return;
  }
  if (preg_match('/[a-zA-Z0-9_]+/', $regexp)) {
    // Interpret omitted brace chars in the regexp as a verbatim text match.
    $regexp = "/$regexp/";
  }
  foreach ($views_plugin_style->view->result as $num => $row) {
    $field_value = views_aggregator_get_cell($field_handler, $num, FALSE);
    if (!preg_match($regexp, $field_value)) {
      unset($views_plugin_style->rendered_fields[$num]);
      unset($views_plugin_style->view->result[$num]);
    }
  }
}

/**
 * Aggregates the supplied view results into grouped rows.
 *
 * This function must be selected for one column (field) in the results table.
 * Its parameters and return value are different from the other aggregation
 * functions.
 *
 * @param \Drupal\views\ResultRow[] $view_results
 *   The result rows as they appear on the view object.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to group rows on.
 * @param string $case
 *   Whether group-inclusion is case-sensitive (the default).
 *
 * @return array
 *   An array of groups, keyed by group value first and row number second.
 */
function views_aggregator_group_and_compress(array $view_results, FieldHandlerInterface $field_handler, string $case = 'case-sensitive'): array {
  $groups = [];
  $is_set = FALSE;
  $is_ci = (strcasecmp($case, 'case-insensitive') === 0) || ($case == t('case-insensitive'));
  foreach ($view_results as $num => $row) {
    // Compression takes place on the rendered values. Two hyperlinks with the
    // same display texts but different hrefs will end up in different groups.
    $group_value = views_aggregator_get_cell($field_handler, $num, TRUE);
    if ($is_ci) {
      $is_set = FALSE;
      foreach (array_keys($groups) as $existing_group) {
        if (strcasecmp($group_value, $existing_group) === 0) {
          $groups[$existing_group][$num] = $row;
          $is_set = TRUE;
          break;
        }
      }
    }
    if (empty($is_set)) {
      $groups[$group_value][$num] = $row;
    }
  }
  return $groups;
}

/**
 * Aggregates a field group as the average amongst its members.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to find the minimum in.
 * @param int|null $precision_group
 *   (optional) The number of decimals, if specified.
 * @param int|null $precision_column
 *   (optional) The number of decimals, if specified.
 *
 * @return array
 *   An array of values, one for each group and one for the column.
 */
function views_aggregator_average(array $groups, FieldHandlerInterface $field_handler, ?int $precision_group, ?int $precision_column): array {
  $values = [];
  $sum_column = 0.0;
  $count_column = 0;
  foreach ($groups as $group => $rows) {
    $sum = 0.0;
    $count = 0;
    foreach ($rows as $num => $row) {
      // Do not count empty or non-numeric cells.
      $cell = vap_num(views_aggregator_get_cell($field_handler, $num, FALSE));
      if ($cell !== FALSE) {
        $sum += $cell;
        $count++;
      }
    }
    $average = ($count == 0) ? 0.0 : $sum / $count;
    $values[$group] = empty($precision_group) ? $average : number_format($average, $precision_group, '.', '');
    $sum_column += $sum;
    $count_column += $count;
  }
  $average_column = ($count_column == 0) ? 0.0 : $sum_column / $count_column;
  $values['column'] = empty($precision_column) ? $average_column : number_format($average_column, $precision_column, '.', '');
  return $values;
}

/**
 * Aggregates a field group as a count of the number of group members.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to count groups members in.
 * @param string|null $group_regexp
 *   (optional) Regexp to count. If omitted all non-empty group values count.
 * @param string|null $column_regexp
 *   (optional) Regexp to count. If omitted all non-empty group values count.
 *
 * @return array
 *   An array of values, one for each group and one for the column.
 */
function views_aggregator_count(array $groups, FieldHandlerInterface $field_handler, ?string $group_regexp = NULL, ?string $column_regexp = NULL): array {
  $values = [];
  $count_column = 0;

  if (isset($group_regexp)) {
    $regexp = $group_regexp;
  }
  elseif (isset($column_regexp)) {
    $regexp = $column_regexp;
  }
  else {
    $regexp = '';
  }

  if (preg_match('/[a-zA-Z0-9_]+/', $regexp)) {
    // Interpret omitted brace chars in the regexp as a verbatim text match.
    $regexp = "/$regexp/";
  }
  foreach ($groups as $group => $rows) {
    $count = 0;
    foreach ($rows as $num => $row) {
      $cell = views_aggregator_get_cell($field_handler, $num, TRUE);
      if (isset($cell) && $cell != '' && (empty($regexp) || preg_match($regexp, $cell))) {
        $count++;
      }
    }
    $values[$group] = $count;
    $count_column += $count;
  }
  $values['column'] = $count_column;
  return $values;
}

/**
 * Aggregates a field group as a count of the unique number of group members.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to count groups members in.
 * @param string|null $group_regexp
 *   (optional) Regexp to count. If omitted all non-empty group values count.
 * @param string|null $column_regexp
 *   (optional) Regexp to count. If omitted all non-empty group values count.
 *
 * @return array
 *   An array of values, one for each group and one for the column.
 */
function views_aggregator_count_unique(array $groups, FieldHandlerInterface $field_handler, ?string $group_regexp = NULL, ?string $column_regexp = NULL): array {
  $values = [];
  $count_column = 0;

  if (isset($group_regexp)) {
    $regexp = $group_regexp;
  }
  elseif (isset($column_regexp)) {
    $regexp = $column_regexp;
  }
  else {
    $regexp = '';
  }

  if (preg_match('/[a-zA-Z0-9_]+/', $regexp)) {
    // Interpret omitted brace chars in the regexp as a verbatim text match.
    $regexp = "/$regexp/";
  }
  foreach ($groups as $group => $rows) {
    $count = 0;
    $cell_values = [];
    foreach ($rows as $num => $row) {
      $cell = views_aggregator_get_cell($field_handler, $num, TRUE);
      if (isset($cell) && $cell != '' && (empty($regexp) || preg_match($regexp, $cell)) && !in_array($cell, $cell_values)) {
        $cell_values[] = $cell;
        $count++;
      }
    }
    $values[$group] = $count;
    $count_column += $count;
  }
  $values['column'] = $count_column;
  return $values;
}

/**
 * Aggregates a field group as the enumeration of its members.
 *
 * The enumeration is sorted "naturally" and duplicates are removed.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to find members of the group.
 * @param string|null $separator_group
 *   (optional) The separator to use in group enumerations, defaults to
 *   '<br/>'.
 * @param string|null $separator_column
 *   (optional) The separator to use for the column enumeration, defaults to
 *   '<br/>'.
 *
 * @return array
 *   An array of values, one for each group.
 */
function views_aggregator_enumerate(array $groups, FieldHandlerInterface $field_handler, ?string $separator_group, ?string $separator_column): array {
  return _views_aggregator_enumerate($groups, $field_handler, $separator_group, $separator_column, TRUE, FALSE);
}

/**
 * Aggregates a field group as the enumeration of its members.
 *
 * The enumeration retains the original order and does not remove duplicates.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to find members of the group.
 * @param string|null $separator_group
 *   (optional) The separator to use in group enumerations, defaults to '<br/>'.
 * @param string|null $separator_column
 *   (optional) The separator to use for the column enumeration, defaults to
 *   '<br/>'.
 *
 * @return array
 *   An array of values, one for each group.
 */
function views_aggregator_enumerate_raw(array $groups, FieldHandlerInterface $field_handler, ?string $separator_group, ?string $separator_column): array {
  return _views_aggregator_enumerate($groups, $field_handler, $separator_group, $separator_column, FALSE, TRUE);
}

/**
 * Aggregates a field group as the enumeration of its members.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to find members of the group.
 * @param string|null $separator_group
 *   (optional) The separator to use in group enumerations, defaults to
 *   '<br/>'.
 * @param string|null $separator_column
 *   (optional) The separator to use for the column enumeration, defaults to
 *   '<br/>'.
 * @param bool $sort
 *   (optional) Whether or not to sort the enumeration. Defaults to TRUE.
 * @param bool $allow_duplicates
 *   (optional) Whether or not to allow duplicates from the enumeration.
 *   Defaults to FALSE.
 *
 * @return array
 *   An array of values, one for each group.
 */
function _views_aggregator_enumerate(array $groups, FieldHandlerInterface $field_handler, ?string $separator_group, ?string $separator_column, bool $sort = TRUE, bool $allow_duplicates = FALSE): array {
  $separator_group = empty($separator_group) ? '<br/>' : $separator_group;
  $separator_column = empty($separator_column) ? '<br/>' : $separator_column;
  $values = ['column' => []];
  foreach ($groups as $group => $rows) {
    $cell_values = [];
    foreach ($rows as $num => $row) {
      $cell = trim(views_aggregator_get_cell($field_handler, $num, TRUE));
      if (!empty($cell)) {
        if ($allow_duplicates || !in_array($cell, $cell_values)) {
          $cell_values[] = $cell;
        }
        if ($allow_duplicates || !in_array($cell, $values['column'])) {
          $values['column'][] = $cell;
        }
      }
    }
    if (count($cell_values) > 1) {
      // After grouping the fields in the group no longer belong to one
      // entity. Cannot easily support hyper-linking, so switch it off.
      unset($field_handler->options['link_to_node']);
      if ($sort) {
        @sort($cell_values, SORT_NATURAL | SORT_FLAG_CASE);
      }
    }
    if ($group != 'column') {
      $values[$group] = implode($separator_group, $cell_values);
    }
  }
  if ($sort) {
    @sort($values['column'], SORT_NATURAL | SORT_FLAG_CASE);
  }
  $values['column'] = implode($separator_column, $values['column']);
  return $values;
}

/**
 * Aggregates a field group as the first member of the group.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to find the first group member in.
 *
 * @return array
 *   An empty array.
 */
function views_aggregator_first(array $groups, FieldHandlerInterface $field_handler): array {
  // This is the default operation, so nothing to do, except for Webforms.
  $values = [];
  foreach ($groups as $group => $rows) {
    $values[$group] = views_aggregator_get_cell($field_handler, key($rows));
  }
  return $values;
}

/**
 * Aggregates a field group as the maximum across its members.
 *
 * Using numbers mixed with non-numeric strings is not recommended.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to find the maximum groups member in.
 *
 * @return array
 *   An array of values, one for each group, plus the 'column' group.
 */
function views_aggregator_maximum(array $groups, FieldHandlerInterface $field_handler): array {
  $values = [];

  foreach ($groups as $group => $rows) {
    $is_first = TRUE;
    $maximum = NULL;
    foreach ($rows as $num => $row) {
      $value = views_aggregator_get_cell($field_handler, $num, FALSE);
      if (isset($value) && trim($value) != '') {
        if ($is_first) {
          $maximum = $value;
          $is_first = FALSE;
        }
        elseif ($value > $maximum) {
          $maximum = $value;
        }
      }
    }
    if (isset($maximum)) {
      $values[$group] = $maximum;
      if (!isset($maximum_column) || $maximum > $maximum_column) {
        $maximum_column = $maximum;
      }
    }
  }
  if (isset($maximum_column)) {
    $values['column'] = $maximum_column;
  }
  return $values;
}

/**
 * Aggregates a field group as the median across its members.
 *
 * This function was written for numbers, but also tries to do a half-decent
 * job of dealing with the median of a group/column of strings.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to calculate the median for.
 *
 * @return array
 *   An array of values, one for each group, plus the 'column' group.
 */
function views_aggregator_median(array $groups, FieldHandlerInterface $field_handler): array {
  $values = [];
  $column_cells = [];
  foreach ($groups as $group => $rows) {
    $group_cells = [];
    foreach ($rows as $num => $row) {
      $cell = views_aggregator_get_cell($field_handler, $num, FALSE);
      if ($cell !== FALSE && trim($cell) != '') {
        $group_cells[] = $cell;
        $column_cells[] = $cell;
      }
    }
    if (!empty($group_cells)) {
      sort($group_cells);
      $m = (int) (count($group_cells) / 2);
      $values[$group] = vap_num($group_cells[$m]) === FALSE || count($group_cells) % 2 ? $group_cells[$m] : ($group_cells[$m] + $group_cells[$m - 1]) / 2;
    }
  }
  if (!empty($column_cells)) {
    sort($column_cells);
    $m = (int) (count($column_cells) / 2);
    $values['column'] = vap_num($column_cells[$m]) === FALSE || count($column_cells) % 2 ? $column_cells[$m] : ($column_cells[$m] + $column_cells[$m - 1]) / 2;
  }
  return $values;
}

/**
 * Aggregates a field group as the minimum across its members.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to find the minimum groups member in.
 *
 * @return array
 *   An array of values, one for each group, plus the 'column' group.
 */
function views_aggregator_minimum(array $groups, FieldHandlerInterface $field_handler): array {
  $values = [];
  foreach ($groups as $group => $rows) {
    $is_first = TRUE;
    $minimum = NULL;
    foreach ($rows as $num => $row) {
      $value = views_aggregator_get_cell($field_handler, $num, FALSE);
      // Ignore empty strings.
      if (isset($value) && trim($value) != '') {
        if ($is_first) {
          $minimum = $value;
          $is_first = FALSE;
        }
        elseif ($value < $minimum) {
          $minimum = $value;
        }
      }
    }
    if (isset($minimum)) {
      $values[$group] = $minimum;
      if (!isset($minimum_column) || $minimum < $minimum_column) {
        $minimum_column = $minimum;
      }
    }
  }
  if (isset($minimum_column)) {
    $values['column'] = $minimum_column;
  }
  return $values;
}

/**
 * Aggregates a field group as a range. Example: 5.5 - 14.9.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column.
 * @param string|null $separator_group
 *   (optional) The range separator between minimum and maximum values of the
 *   group range. Defaults to ' - '.
 * @param string|null $separator_column
 *   (optional) The range separator between minimum and maximum values of the
 *   column range. Defaults to ' - '.
 *
 * @return array
 *   An array of ranges, one for each group and one for the 'column'.
 *   Each range is an array of two elements, so they can be individually
 *   rendered.
 */
function views_aggregator_range(array $groups, FieldHandlerInterface $field_handler, ?string $separator_group = NULL, ?string $separator_column = NULL): array {
  $separator_group = empty($separator_group) ? ' - ' : $separator_group;
  $separator_column = empty($separator_column) ? ' - ' : $separator_column;
  $values = [];
  foreach ($groups as $group => $rows) {
    $is_first = TRUE;
    foreach ($rows as $num => $row) {
      $value = views_aggregator_get_cell($field_handler, $num, FALSE);
      if ($is_first) {
        $minimum = $maximum = $value;
        $is_first = FALSE;
      }
      elseif (isset($value) && $value < $minimum) {
        $minimum = $value;
      }
      elseif (isset($value) && $value > $maximum) {
        $maximum = $value;
      }
    };
    $values[$group] = ($minimum == $maximum) ? $minimum : implode($separator_group, [$minimum, $maximum]);
    if (!isset($minimum_column) || $minimum < $minimum_column) {
      $minimum_column = $minimum;
    }
    if (!isset($maximum_column) || $maximum > $maximum_column) {
      $maximum_column = $maximum;
    }
  }
  if ($minimum_column == $maximum_column) {
    $values['column'] = $minimum_column;
  }
  else {
    $values['column'] = implode($separator_column, [$minimum_column, $maximum_column]);
  }
  return $values;
}

/**
 * Aggregates a field group as a word, phrase or number.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column.
 * @param string|null $replace_text
 *   (optional) Specifies the replacement text to use.
 * @param string|null $column_label
 *   (optional) Specifies a label placed the bottom of the column.
 *
 * @return array
 *   An array of values, one for each group and one for the 'column'.
 */
function views_aggregator_replace(array $groups, FieldHandlerInterface $field_handler, ?string $replace_text = NULL, ?string $column_label = NULL): array {
  $values = [];
  foreach ($groups as $group => $rows) {
    if (isset($replace_text)) {
      $values[$group] = $replace_text;
    }
    if (count($rows) > 1 && isset($replace_text)) {
      // With more than one field in a group the fields no longer belong to one
      // particular entity. Cannot support hyper-linking, so switch it off.
      // Unfortunately this applies to the entire column.
      unset($field_handler->options['link_to_node']);
    }
  }
  if (isset($column_label)) {
    $values['column'] = $column_label;
  }
  return $values;
}

/**
 * Aggregates a field group as the sum of its members.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to sum groups in.
 *
 * @return array
 *   An array of values, one for each group, plus the 'column' group.
 */
function views_aggregator_sum(array $groups, FieldHandlerInterface $field_handler): array {
  $values = [];
  $sum_column = 0.0;
  foreach ($groups as $group => $rows) {
    $sum = 0.0;
    foreach ($rows as $num => $row) {
      $cell = vap_num(views_aggregator_get_cell($field_handler, $num, FALSE));
      if ($cell !== FALSE) {
        $sum += $cell;
      }
    };
    $values[$group] = $sum;
    $sum_column += $sum;
  }
  $values['column'] = $sum_column;
  return $values;
}

/**
 * Aggregates a field group as the tally of its members.
 *
 * @param array $groups
 *   An array of groups of rows, each group indexed by group value.
 * @param \Drupal\views\Plugin\views\field\FieldHandlerInterface $field_handler
 *   The handler for the view column to find members of the group.
 * @param string|null $separator_group
 *   (optional) The separator to use between tallies in a group, defaults to
 *   '<br/>'.
 * @param string|null $separator_column
 *   (optional) The separator to use between tallies in the totals field,
 *   defaults to '<br/>'.
 *
 * @return array
 *   An array of values, one for each group.
 */
function views_aggregator_tally(array $groups, FieldHandlerInterface $field_handler, ?string $separator_group, ?string $separator_column): array {
  $separator_group = empty($separator_group) ? '<br/>' : $separator_group;
  $separator_column = empty($separator_column) ? '<br/>' : $separator_column;
  $values = ['column' => []];
  foreach ($groups as $group => $rows) {
    $tally = [];
    foreach ($rows as $num => $row) {
      $cell = trim(views_aggregator_get_cell($field_handler, $num, TRUE));
      if (empty($cell)) {
        // Not tallying empty values.
        $values[$group] = NULL;
        break;
      }
      if (isset($tally[$cell])) {
        $tally[$cell]++;
      }
      else {
        $tally[$cell] = 1;
      }
    }
    if (count($tally) > 1) {
      // With more than one field in a group the fields no longer belong to one
      // particular entity. Cannot support hyper-linking, so switch it off.
      // Unfortunately this applies to the entire column.
      unset($field_handler->options['link_to_node']);
    }
    ksort($tally, SORT_NATURAL | SORT_FLAG_CASE);

    $rendered_tally = [];
    foreach ($tally as $cell => $count) {
      $rendered_tally[] = "$cell ($count)";
    }
    $values[$group] = implode($separator_group, $rendered_tally);
  }
  // Note, column not actually computed.
  $values['column'] = implode($separator_column, $values['column']);
  return $values;
}

/**
 * Function to extract a double from a string, auto-skipping non-numeric chars.
 *
 * Commas and spaces are ignored.
 * Scientific notation, e.g., 1.23E-45, is NOT supported, it will return 1.23
 *
 * @param string $string
 *   A string representation of a double-precision floating-point number.
 *
 * @return float|false
 *   Returns a floating-point value, or FALSE if no number could be found
 *   in the string.
 */
function vap_num(string $string): float|false {
  // Strip out any spaces and thousand makers.
  $stripped = str_replace([' ', ','], '.', $string);
  $dots_count = substr_count($stripped, '.');
  if ($dots_count > 1) {
    // Leave last dot as decimal separator, get rid of the rest.
    $stripped = preg_replace('/\./', '', $stripped, $dots_count - 1);
  }
  $value = preg_match('/[-+]?\d*\.?\d+/', $stripped, $matches) ? (double) $matches[0] : FALSE;
  return $value;
}
