<?php

/**
 * @file
 * Mark migrated feature stories: category 'feature_story', promote = D7 front.
 *
 * cas_feature_story_to_story now sets both at import; this brings an
 * already-migrated database up to date without re-running the migration.
 * The D7 "front" flag is read from the migrate source connection.
 *
 * Usage: drush scr scripts-dev/backfill_feature_stories.php
 */

use Drupal\Core\Database\Database;

$db = \Drupal::database();
$d7 = Database::getConnection('default', 'migrate');
$map = $db->query('SELECT sourceid1, destid1 FROM {migrate_map_cas_feature_story_to_story} WHERE destid1 IS NOT NULL')->fetchAllKeyed();
if (!$map) {
  print "No feature stories mapped.\n";
  return;
}
$front = $d7->query('SELECT entity_id FROM {field_data_field_feature_story_front} WHERE field_feature_story_front_value = 1 AND entity_type = :t', [':t' => 'node'])->fetchCol();
$front = array_flip(array_map('intval', $front));
$storage = \Drupal::entityTypeManager()->getStorage('node');
$updated = $promoted = 0;
foreach (array_chunk(array_keys($map), 50, TRUE) as $chunk) {
  foreach ($storage->loadMultiple(array_intersect_key($map, array_flip($chunk))) as $node) {
    $d7nid = array_search($node->id(), $map);
    $cats = array_column($node->get('field_story_category')->getValue(), 'value');
    $changed = FALSE;
    if (!in_array('feature_story', $cats, TRUE)) {
      $node->get('field_story_category')->appendItem(['value' => 'feature_story']);
      $changed = TRUE;
    }
    $want = isset($front[(int) $d7nid]) ? 1 : 0;
    if ((int) $node->isPromoted() !== $want) {
      $node->setPromoted((bool) $want);
      $changed = TRUE;
      $promoted += $want;
    }
    if ($changed) {
      $node->setNewRevision(FALSE);
      $node->setSyncing(TRUE);
      $node->save();
      $updated++;
    }
  }
}
printf("Feature stories: %d mapped, %d updated, %d promoted (D7 front)\n", count($map), $updated, $promoted);
