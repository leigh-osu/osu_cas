<?php

/**
 * @file
 * Publish dates for article-origin stories that migrated without one.
 *
 * "News" in D7 was the article type; in D10 the discriminator is a publish
 * date (news_items_by_group filters on it -- feature stories never carried
 * one). D7 sorted date-less articles by created, so created becomes their
 * publish date. One-time repair; rebuilds get the same fallback in the
 * cas_article_to_story migration.
 *
 * Usage: drush scr scripts-dev/backfill_article_publish_dates.php
 */

$db = \Drupal::database();
$nids = $db->query("
  SELECT m.destid1 FROM {migrate_map_cas_article_to_story} m
  WHERE m.destid1 IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM {node__field_story_publish_date} d WHERE d.entity_id = m.destid1)
")->fetchCol();
$storage = \Drupal::entityTypeManager()->getStorage('node');
$set = 0;
foreach (array_chunk($nids, 50) as $chunk) {
  foreach ($storage->loadMultiple($chunk) as $node) {
    $node->set('field_story_publish_date', date('Y-m-d', $node->getCreatedTime()));
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    $set++;
  }
  $storage->resetCache($chunk);
}
printf("Publish dates set from created: %d\n", $set);
