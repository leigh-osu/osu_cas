<?php

/**
 * @file
 * Subgroup/division terms onto profiles from D7 field_division.
 *
 * FW's division-filtered people listings (fw_profiles) key on the
 * profile_subgroups terms D7 kept on the employee profile; the profile
 * migration now maps them (field_profile_subgroups), and this repairs a
 * database migrated before that. Term ids are preserved.
 *
 * Usage: drush scr scripts-dev/backfill_profile_subgroups.php
 */

$migrate = \Drupal\Core\Database\Database::getConnection('default', 'migrate');
$rows = $migrate->query("
  SELECT p.uid, f.field_division_tid AS tid
  FROM {field_data_field_division} f
  JOIN {profile} p ON p.pid = f.entity_id AND p.type = 'agricultural_sciences'
  WHERE f.entity_type = 'profile2'
  ORDER BY p.uid, f.delta
")->fetchAll();
$by_uid = [];
foreach ($rows as $row) {
  $by_uid[$row->uid][] = (int) $row->tid;
}
$map = \Drupal::database()->query('SELECT sourceid1, destid1 FROM {migrate_map_upgrade_d7_user_to_profile} WHERE destid1 IS NOT NULL')->fetchAllKeyed();
$terms = array_flip(array_map('intval', \Drupal::database()->query("SELECT tid FROM {taxonomy_term_field_data} WHERE vid='profile_subgroups'")->fetchCol()));
$storage = \Drupal::entityTypeManager()->getStorage('node');
$set = $same = $no_profile = 0;
foreach ($by_uid as $uid => $tids) {
  $nid = $map[$uid] ?? NULL;
  if (!$nid || !($node = $storage->load($nid))) {
    $no_profile++;
    continue;
  }
  $tids = array_values(array_filter($tids, fn($t) => isset($terms[$t])));
  $current = array_map('intval', array_column($node->get('field_profile_subgroups')->getValue(), 'target_id'));
  if ($current === $tids) {
    $same++;
    continue;
  }
  $node->set('field_profile_subgroups', $tids);
  $node->setNewRevision(FALSE);
  $node->setSyncing(TRUE);
  $node->save();
  $set++;
}
printf("Set: %d  Already set: %d  No profile: %d\n", $set, $same, $no_profile);
