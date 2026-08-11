<?php

/**
 * @file
 * Backfill field_profile_user on existing profiles.
 *
 * upgrade_d7_user_to_profile populates it going forward, so a rebuilt site
 * needs none of this — it exists for databases built before that mapping was
 * added, where the Profile tab would otherwise be invisible.
 *
 * Uses the same relationship as the migration rather than copying node
 * authorship: the profile's D7 source uid is looked up in the two user
 * migrations, so a profile whose account never migrated is left empty
 * instead of pointing at uid 1.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/backfill_profile_user.php
 */

$db = \Drupal::database();
$storage = \Drupal::entityTypeManager()->getStorage('node');

$maps = ['migrate_map_upgrade_d7_users_with_roles', 'migrate_map_cas_d7_active_users'];
$user_of_source = [];
foreach ($maps as $map) {
  if (!$db->schema()->tableExists($map)) {
    continue;
  }
  foreach ($db->query('SELECT sourceid1, destid1 FROM {' . $map . '} WHERE destid1 IS NOT NULL') as $r) {
    $user_of_source[(int) $r->sourceid1] = (int) $r->destid1;
  }
}
printf("D7 users with a migrated account: %d\n", count($user_of_source));

$profile_source = [];
if ($db->schema()->tableExists('migrate_map_upgrade_d7_user_to_profile')) {
  foreach ($db->query('SELECT sourceid1, destid1 FROM {migrate_map_upgrade_d7_user_to_profile} WHERE destid1 IS NOT NULL') as $r) {
    $profile_source[(int) $r->destid1] = (int) $r->sourceid1;
  }
}
printf("migrated profiles: %d\n", count($profile_source));

$set = $already = $no_account = 0;
foreach (array_chunk(array_keys($profile_source), 200) as $chunk) {
  foreach ($storage->loadMultiple($chunk) as $node) {
    if (!$node->hasField('field_profile_user')) {
      continue;
    }
    $uid = $user_of_source[$profile_source[(int) $node->id()]] ?? NULL;
    if ($uid === NULL) {
      $no_account++;
      continue;
    }
    if (!$node->get('field_profile_user')->isEmpty()
      && (int) $node->get('field_profile_user')->target_id === $uid) {
      $already++;
      continue;
    }
    $node->set('field_profile_user', ['target_id' => $uid]);
    $node->save();
    $set++;
  }
}
printf("set: %d | already correct: %d | no migrated account (left empty): %d\n", $set, $already, $no_account);
