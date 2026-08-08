<?php

/**
 * @file
 * Post-rebuild fix: repair playlist-junk YouTube URLs; unpublish videos
 * whose remote targets are gone.
 *
 * Two repairs from the 2026-08 video audit (Roger):
 * - D7 stored some YouTube media as youtube://v/<id>/l/<playlist>; the
 *   migrated watch URL keeps the playlist as extra path segments and
 *   YouTube's oEmbed endpoint 400s on it though the video is fine. Trim
 *   every affected media URL (the migration now does this too via
 *   cas_clean_youtube_url; this heals a DB migrated before that).
 * - 20 video nodes point at provider-deleted (404) or private (403)
 *   YouTube/Vimeo targets — dead on live D7 as well. Unpublish them so
 *   visitors stop landing on broken embeds. Nid list frozen from the
 *   audit; see ~/Desktop/osu-cas-reports/dead_videos.csv.
 *
 * Idempotent. Run:
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_dead_videos.php
 */

use Drupal\node\Entity\Node;
use Drupal\osu_migrations_cas\Plugin\migrate\process\CasCleanYoutubeUrl;

// -- YouTube playlist junk ---------------------------------------------------
$db = \Drupal::database();
$fixed = 0;
foreach (['media__field_media_oembed_video', 'media_revision__field_media_oembed_video'] as $table) {
  $rows = $db->query("SELECT entity_id, revision_id, delta, langcode, field_media_oembed_video_value v FROM {$table} WHERE field_media_oembed_video_value LIKE '%youtube.com/watch?v=%/%'")->fetchAll();
  foreach ($rows as $row) {
    $clean = CasCleanYoutubeUrl::cleanUrl($row->v);
    if ($clean !== $row->v) {
      $db->update($table)
        ->fields(['field_media_oembed_video_value' => $clean])
        ->condition('entity_id', $row->entity_id)
        ->condition('revision_id', $row->revision_id)
        ->condition('delta', $row->delta)
        ->condition('langcode', $row->langcode)
        ->execute();
      $fixed++;
    }
  }
}
print "cleaned $fixed media URL rows\n";
if ($fixed) {
  \Drupal::entityTypeManager()->getStorage('media')->resetCache();
}

// -- Dead-target video nodes -------------------------------------------------
$dead_nids = [
  // 404 at the provider (deleted).
  59016, 80721, 80726, 80731, 80736, 80746, 80761, 80766, 80771, 80776,
  80781, 80786, 82791, 211306, 229516,
  // 403 (private / unavailable).
  16855, 83416, 124801, 216191, 229511,
];
$unpublished = 0;
foreach ($dead_nids as $nid) {
  $node = Node::load($nid);
  if ($node && $node->bundle() === 'video' && $node->isPublished()) {
    $node->setUnpublished();
    $node->save();
    $unpublished++;
  }
}
print "unpublished $unpublished dead-target video nodes\n";
