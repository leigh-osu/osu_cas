<?php

/**
 * @file
 * Capture each group's landing page into field_group_home_page.
 *
 * The D7 convention was implicit: a group's front page is the group_node
 * whose title exactly matches the group's label. Manzanita used to resolve
 * that at render time; the field makes it explicit and editable, and the
 * theme now reads ONLY the field. This script applies the old heuristic
 * exactly once per group — groups that already have a value are left
 * alone, so editorial choices survive reruns.
 *
 * Run late in the rebuild, after groups and their content exist:
 *   ddev drush --uri=agsci.oregonstate.edu scr scripts-dev/populate_group_home_pages.php
 */

use Drupal\group\Entity\Group;

$db = \Drupal::database();
$set = 0;
$missing = [];
foreach (Group::loadMultiple() as $group) {
  if (!$group->hasField('field_group_home_page') || !$group->get('field_group_home_page')->isEmpty()) {
    continue;
  }
  $nid = $db->queryRange(
    'SELECT n.nid
     FROM {group_relationship_field_data} gr
     JOIN {node_field_data} n ON n.nid = gr.entity_id
     WHERE gr.gid = :gid
       AND gr.plugin_id LIKE :plugin
       AND n.title = :label
       AND n.status = 1
       AND n.default_langcode = 1
     ORDER BY n.nid ASC',
    0, 1,
    [':gid' => $group->id(), ':plugin' => 'group_node:%', ':label' => trim((string) $group->label())]
  )->fetchField();
  if ($nid) {
    $group->set('field_group_home_page', $nid);
    $group->save();
    $set++;
  }
  else {
    $missing[] = $group->id() . ' (' . $group->label() . ')';
  }
}

printf("home pages set: %d; groups with no matching node: %d\n", $set, count($missing));
if ($missing) {
  print "  " . implode("\n  ", array_slice($missing, 0, 20)) . "\n";
  if (count($missing) > 20) {
    print "  ... and " . (count($missing) - 20) . " more\n";
  }
}
