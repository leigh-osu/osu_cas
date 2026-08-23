<?php

/**
 * @file
 * Hooks provided by the text field formatter module.
 */

/**
 * Alters Wrap tag options for the field formatter.
 *
 * @param array $wrappers
 *   The default wrapper tags options array.
 */
function hook_default_wrap_tags_alter(array &$wrappers) {
  // Add additional wrapper.
  $wrappers['p'] = t('P');
}
