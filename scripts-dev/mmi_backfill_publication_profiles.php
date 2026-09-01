<?php

/**
 * @file
 * Backfill field_pub_osu_profile on the MMI publications.
 *
 * The MMI adaptation of backfill_publication_profiles.php. D7 rendered
 * each person's publication list by joining biblio_contributor_data
 * .drupal_uid (44 linked contributors — the PIs and researchers — credited
 * on 728 of the 735 MMI entries) through biblio_contributor; no migration
 * reads that column, so field_pub_osu_profile arrived empty and every MMI
 * profile's "My Publications" listing (osu_publications:block_1, argument-
 * bound to this field) had nothing to show.
 *
 * Resolves each author uid to their profile node through
 * migrate_map_mmi_profiles and each biblio nid to its publication through
 * migrate_map_mmi_biblio, preserving D7 contributor rank so co-author
 * order carries over. Contributors whose uid has no migrated profile are
 * skipped and reported.
 *
 * Idempotent: publications that already carry the field are left alone.
 *
 * Usage: drush scr scripts-dev/mmi_backfill_publication_profiles.php -- --dry-run
 *        drush scr scripts-dev/mmi_backfill_publication_profiles.php
 */

use Drupal\Core\Database\Database;

$dry = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$d7 = Database::getConnection('default', 'migrate_mmi');
$db = \Drupal::database();

$pub_map = $db->query('SELECT sourceid1, destid1 FROM {migrate_map_mmi_biblio} WHERE destid1 IS NOT NULL')->fetchAllKeyed();
$profile_map = $db->query('SELECT sourceid1, destid1 FROM {migrate_map_mmi_profiles} WHERE destid1 IS NOT NULL')->fetchAllKeyed();
printf("maps: %d publications, %d profiles\n", count($pub_map), count($profile_map));

// Author -> publication, in D7 author order. `rank` is reserved in MySQL 8.
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
  if (!in_array($profile, $by_node[$nid] ?? [], TRUE)) {
    $by_node[$nid][] = $profile;
  }
}
printf(
  "resolvable: %d publications, %d links (skipped %d with no migrated publication, %d contributions by %d uids without a profile)\n",
  count($by_node), array_sum(array_map('count', $by_node)), $no_publication, $no_profile, count($orphan_uids)
);
if ($orphan_uids) {
  print '  uids without a profile: ' . implode(', ', array_keys($orphan_uids)) . "\n";
}

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
foreach (array_slice($profiles_seen, 0, 5, TRUE) as $p => $n) {
  $node = $storage->load($p);
  printf("  %-40s %d publications\n", $node ? $node->label() : "profile $p", $n);
}
printf(
  "%s field_pub_osu_profile on %d publications across %d profiles (%d already set, %d unusable)\n",
  $dry ? 'Would set' : 'Set', $updated, count($profiles_seen), $skipped, $missing
);
if (!$dry && $updated) {
  \Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list']);
}
