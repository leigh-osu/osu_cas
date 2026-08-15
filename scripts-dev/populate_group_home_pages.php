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

// Groups whose landing the title-match heuristic cannot find: the landing
// node's title differs from the group label (WVBS root is "Admissions
// Status", Buhl's is "Home", the Schmidt lab drops "& Biogeoscience", ...),
// or no landing node ever existed (Main and News and Accolades fronted D7
// views) — those two point at the site front until an editor gives them a
// real page. Applied only where the field is still empty, like the
// heuristic, so editorial choices always win.
$overrides = [
  8 => 302219,       // Main -> site front (no D7 landing; fronted a view)
  85166 => 85171,    // INFEWS
  89321 => 89326,    // Value-Added Food Product Development (/foodweb)
  109891 => 302219,  // News and Accolades -> site front (newsroom was a view)
  218401 => 219271,  // Buhl Outreach Team -> its "Home" page
  232571 => 232576,  // EMT Guide -> /emt-gs-guide root
  248306 => 248311,  // Machine Learning in Physical Geography (/schmidt-lab)
  261631 => 261636,  // Willamette Valley Bird Symposium (/wvbs)
  270951 => 270956,  // Willamette Valley Field Crops
];

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
  elseif (isset($overrides[(int) $group->id()])) {
    $group->set('field_group_home_page', $overrides[(int) $group->id()]);
    $group->save();
    $set++;
  }
  else {
    $missing[] = $group->id() . ' (' . $group->label() . ')';
  }
}

// Every group home page renders without its node title -- the header's
// group-size site name IS the page's title there. Merge ALL current home
// pages (field values, however they were set) into exclude_node_title's
// per-node state list, the same list import_hide_title.php feeds; the
// module only honours it for the 'page' bundle, which is every home page
// today. Idempotent set-merge, so editor additions survive.
$home_nids = $db->query(
  'SELECT DISTINCT field_group_home_page_target_id
   FROM {group__field_group_home_page} WHERE deleted = 0'
)->fetchCol();
$state = \Drupal::state();
$list = $state->get('exclude_node_title_nid_list') ?: [];
$merged = array_values(array_unique(array_merge($list, array_map('intval', $home_nids))));
$state->set('exclude_node_title_nid_list', $merged);
printf("hide-title: %d home pages merged; exclude list now %d nids\n", count($home_nids), count($merged));

printf("home pages set: %d; groups with no matching node: %d\n", $set, count($missing));
if ($missing) {
  print "  " . implode("\n  ", array_slice($missing, 0, 20)) . "\n";
  if (count($missing) > 20) {
    print "  ... and " . (count($missing) - 20) . " more\n";
  }
}
