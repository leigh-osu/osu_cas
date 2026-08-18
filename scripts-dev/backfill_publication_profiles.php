<?php

/**
 * @file
 * Backfill field_pub_osu_profile so publications know whose profile they
 * belong on.
 *
 * D7 rendered a "My Publications" list inside every person profile. The link
 * behind it was not a field at all: biblio stored it in
 * biblio_contributor_data.drupal_uid — 490 author records carrying the account
 * of the person who wrote them — and the view joined through
 * biblio_contributor. No migration reads that table, so field_pub_osu_profile
 * arrived empty and the D10 view (osu_publications:block_1, which is already
 * argument-bound to field_pub_osu_profile_target_id) had nothing to show.
 *
 * This resolves each author account to the profile node it became, through
 * migrate_map_upgrade_d7_user_to_profile, and each biblio node to its
 * publication through migrate_map_upgrade_d7_biblio_publication. Contributor
 * rank is preserved, so the profile order on a co-authored publication matches
 * the D7 author order.
 *
 * 124 of the 475 author accounts were already deleted in D7 and so have no
 * profile on either side; their contributions are skipped and reported.
 *
 * Idempotent: publications that already carry the field are left alone.
 *
 * Usage: drush scr scripts-dev/backfill_publication_profiles.php -- --dry-run
 *        drush scr scripts-dev/backfill_publication_profiles.php
 */

use Drupal\Core\Database\Database;

$dry = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$d7 = Database::getConnection('default', 'migrate');
$db = \Drupal::database();

$pub_map = $db->query('SELECT sourceid1, destid1 FROM {migrate_map_upgrade_d7_biblio_publication} WHERE destid1 IS NOT NULL')->fetchAllKeyed();
$profile_map = $db->query('SELECT sourceid1, destid1 FROM {migrate_map_upgrade_d7_user_to_profile} WHERE destid1 IS NOT NULL')->fetchAllKeyed();
printf("maps: %d publications, %d profiles\n", count($pub_map), count($profile_map));

// Author -> publication, in D7 author order.
// `rank` is a reserved word in MySQL 8 and biblio_contributor uses it as a
// column name, so it has to be quoted.
$rows = $d7->query('
  SELECT bc.nid, bcd.drupal_uid AS uid, MIN(bc.`rank`) AS author_rank
  FROM {biblio_contributor} bc
  JOIN {biblio_contributor_data} bcd ON bcd.cid = bc.cid
  WHERE bcd.drupal_uid > 0
  GROUP BY bc.nid, bcd.drupal_uid
  ORDER BY bc.nid, author_rank
')->fetchAll();

$by_node = [];
$no_publication = $no_profile = 0;
$orphan_uids = [];
foreach ($rows as $r) {
  if (!isset($pub_map[$r->nid])) {
    $no_publication++;
    continue;
  }
  if (!isset($profile_map[$r->uid])) {
    $no_profile++;
    $orphan_uids[$r->uid] = TRUE;
    continue;
  }
  $nid = $pub_map[$r->nid];
  $profile = (int) $profile_map[$r->uid];
  // A merged contributor can appear twice on one node.
  if (!in_array($profile, $by_node[$nid] ?? [], TRUE)) {
    $by_node[$nid][] = $profile;
  }
}
printf(
  "resolvable: %d publications, %d links (skipped %d with no migrated publication, %d authored by %d accounts deleted before the migration)\n",
  count($by_node), array_sum(array_map('count', $by_node)), $no_publication, $no_profile, count($orphan_uids)
);

$storage = \Drupal::entityTypeManager()->getStorage('node');
$profiles_seen = [];
$updated = $skipped = $missing = 0;
foreach (array_chunk($by_node, 200, TRUE) as $chunk) {
  $nodes = $storage->loadMultiple(array_keys($chunk));
  foreach ($chunk as $nid => $targets) {
    $node = $nodes[$nid] ?? NULL;
    if (!$node || !$node->hasField('field_pub_osu_profile')) {
      $missing++;
      continue;
    }
    if (!$node->get('field_pub_osu_profile')->isEmpty()) {
      $skipped++;
      continue;
    }
    // Drop references to profiles that no longer exist.
    $targets = array_values(array_filter($targets, fn($p) => $storage->load($p) !== NULL));
    if (!$targets) {
      $missing++;
      continue;
    }
    foreach ($targets as $p) {
      $profiles_seen[$p] = ($profiles_seen[$p] ?? 0) + 1;
    }
    $updated++;
    if ($dry) {
      continue;
    }
    $node->set('field_pub_osu_profile', array_map(fn($p) => ['target_id' => $p], $targets));
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
  }
  $storage->resetCache();
}

arsort($profiles_seen);
$top = array_slice($profiles_seen, 0, 5, TRUE);
foreach ($top as $p => $n) {
  $node = $storage->load($p);
  printf("  %-32s %d publications\n", $node ? $node->label() : "profile $p", $n);
}
printf(
  "%s field_pub_osu_profile on %d publications across %d profiles (%d already set, %d unusable)\n",
  $dry ? 'Would set' : 'Set', $updated, count($profiles_seen), $skipped, $missing
);
if (!$dry && $updated) {
  \Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list']);
}
