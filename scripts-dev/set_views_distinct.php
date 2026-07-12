<?php

/**
 * @file
 * Force DISTINCT on every node-based view.
 *
 * Group 2.x checks node access in views by LEFT JOINing
 * group_relationship_field_data (one row per group the node belongs to), so
 * any node placed in more than one group repeats once per group in every
 * node listing (drupal.org/project/group/issues/3172135). The upstream fix is
 * the views "distinct" query option; this sets it on all node-based views so
 * it survives a full site rebuild. Idempotent — run via:
 *   ddev drush scr scripts-dev/set_views_distinct.php
 */

$storage = \Drupal::entityTypeManager()->getStorage('view');
foreach ($storage->loadMultiple() as $view) {
  if (!in_array($view->get('base_table'), ['node_field_data', 'node_field_revision'], TRUE)) {
    continue;
  }
  $changed = FALSE;
  $displays = $view->get('display');
  foreach ($displays as $id => $display) {
    // The query plugin settings live on the default display unless a display
    // overrides them, so set the flag wherever a query block exists.
    if ($id === 'default' || isset($display['display_options']['query'])) {
      if (empty($displays[$id]['display_options']['query']['options']['distinct'])) {
        $displays[$id]['display_options']['query']['type'] ??= 'views_query';
        $displays[$id]['display_options']['query']['options']['distinct'] = TRUE;
        $changed = TRUE;
      }
    }
  }
  if ($changed) {
    $view->set('display', $displays);
    $view->save();
    echo "distinct set: " . $view->id() . "\n";
  }
}
echo "done\n";
