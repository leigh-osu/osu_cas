<?php

/**
 * @file
 * Copy D7 og_membership field_membership_type onto profile group placements.
 *
 * D7 stored a person's role per group (Faculty, Graduate Student, Emeritus, …)
 * on the user↔group og_membership row. D10's counterpart entity is the
 * profile node's group_relationship (plugin group_node:osu_profile), which
 * now carries field_membership_type. This maps each typed D7 membership
 * (uid, gid, tid) to its D10 relationship row via the migrate maps —
 * user_to_profile for uid → profile nid; group ids are preserved — and sets
 * the term (tids are preserved by the taxonomy migration). Idempotent; run
 * after profiles and group content exist (rebuild section 7).
 *
 * Usage: drush scr scripts-dev/populate_profile_membership_types.php
 */

$db = \Drupal::database();
$migrate = \Drupal\Core\Database\Database::getConnection('default', 'migrate');

// Typed D7 memberships: user ↔ group with a membership_type value.
$rows = $migrate->query("
  SELECT om.etid AS uid, om.gid, mt.field_membership_type_target_id AS tid
  FROM {og_membership} om
  JOIN {field_data_field_membership_type} mt
    ON mt.entity_id = om.id AND mt.entity_type = 'og_membership'
  WHERE om.entity_type = 'user' AND om.group_type = 'node'
")->fetchAll();

// uid -> profile nid.
$profile_map = $db->query('SELECT sourceid1, destid1 FROM {migrate_map_upgrade_d7_user_to_profile} WHERE destid1 IS NOT NULL')->fetchAllKeyed();
// D10 term ids present (tids are preserved; guard against pruned terms).
$terms = $db->query("SELECT tid FROM {taxonomy_term_field_data} WHERE vid = 'membership_types'")->fetchCol();
$terms = array_flip(array_map('intval', $terms));

$storage = \Drupal::entityTypeManager()->getStorage('group_content');
$set = $same = $no_profile = $no_placement = $no_term = 0;
foreach ($rows as $row) {
  $nid = $profile_map[$row->uid] ?? NULL;
  if (!$nid) {
    $no_profile++;
    continue;
  }
  if (!isset($terms[(int) $row->tid])) {
    $no_term++;
    continue;
  }
  $ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('plugin_id', 'group_node:osu_profile')
    ->condition('gid', $row->gid)
    ->condition('entity_id', $nid)
    ->execute();
  if (!$ids) {
    $no_placement++;
    continue;
  }
  foreach ($storage->loadMultiple($ids) as $relationship) {
    if ((int) ($relationship->get('field_membership_type')->target_id ?? 0) === (int) $row->tid) {
      $same++;
      continue;
    }
    $relationship->set('field_membership_type', $row->tid);
    $relationship->save();
    $set++;
  }
}
printf("Set: %d  Already set: %d  No profile for uid: %d  Profile not placed in group: %d  Term missing: %d\n",
  $set, $same, $no_profile, $no_placement, $no_term);
