<?php

/**
 * @file
 * MMI video repair: trim YouTube playlist junk; report dead remote targets.
 *
 * The MMI adaptation of fix_dead_videos.php, scoped to the 42 MMI
 * remote-video media (via migrate_map_mmi_media_remote_video):
 * - 3 media carry youtube://v/<id>/l/<playlist>-derived watch URLs that
 *   400 at YouTube's oEmbed endpoint, and 1 is playlist-only (youtube://l/)
 *   which media_remote_video passed through raw. The migration now handles
 *   both (cas_clean_youtube_url + str_replace in mmi_media_remote_video);
 *   this heals a DB migrated before that, re-saving fixed media so their
 *   oEmbed thumbnails re-queue.
 * - Each MMI video's oEmbed endpoint is probed; dead targets (deleted or
 *   private at the provider) are REPORTED to a CSV for triage, not
 *   unpublished — MMI videos live inside album galleries and layouts, so
 *   what to do with a dead one is an editorial call.
 *
 * Idempotent. Run via mmi_migrate.sh section 10.
 */

use Drupal\osu_migrations_cas\Plugin\migrate\process\CasCleanYoutubeUrl;

$db = \Drupal::database();

$mids = array_map('intval', $db->query(
  'SELECT destid1 FROM {migrate_map_mmi_media_remote_video} WHERE destid1 IS NOT NULL'
)->fetchCol());
print count($mids) . " MMI remote-video media\n";
if (!$mids) {
  return;
}

// -- Malformed YouTube URLs (playlist junk, raw playlist uris) ---------------
$fixed = 0;
$fixed_mids = [];
foreach (['media__field_media_oembed_video', 'media_revision__field_media_oembed_video'] as $table) {
  $rows = $db->query(
    "SELECT entity_id, revision_id, delta, langcode, field_media_oembed_video_value v
     FROM {" . $table . "}
     WHERE entity_id IN (:mids[])
       AND (field_media_oembed_video_value LIKE '%youtube.com/watch?v=%/%'
         OR field_media_oembed_video_value LIKE 'youtube://l/%')",
    [':mids[]' => $mids]
  )->fetchAll();
  foreach ($rows as $row) {
    $clean = CasCleanYoutubeUrl::cleanUrl(
      str_replace('youtube://l/', 'https://www.youtube.com/playlist?list=', $row->v)
    );
    if ($clean !== $row->v) {
      $db->update($table)
        ->fields(['field_media_oembed_video_value' => $clean])
        ->condition('entity_id', $row->entity_id)
        ->condition('revision_id', $row->revision_id)
        ->condition('delta', $row->delta)
        ->condition('langcode', $row->langcode)
        ->execute();
      $fixed++;
      $fixed_mids[(int) $row->entity_id] = TRUE;
    }
  }
}
print "cleaned $fixed media URL rows\n";
if ($fixed_mids) {
  // The raw update bypassed Media::preSave, so the thumbnail still shows the
  // pre-repair state; refetch it from the repaired URL directly.
  $media_storage = \Drupal::entityTypeManager()->getStorage('media');
  $media_storage->resetCache(array_keys($fixed_mids));
  foreach ($media_storage->loadMultiple(array_keys($fixed_mids)) as $media) {
    $media->updateQueuedThumbnail();
    $media->save();
  }
}

// -- Probe remote targets ----------------------------------------------------
$client = \Drupal::httpClient();
$dead = [];
$urls = $db->query(
  'SELECT entity_id, field_media_oembed_video_value v FROM {media__field_media_oembed_video} WHERE entity_id IN (:mids[])',
  [':mids[]' => $mids]
)->fetchAllKeyed();
foreach ($urls as $mid => $url) {
  $endpoint = str_contains($url, 'vimeo.com')
    ? 'https://vimeo.com/api/oembed.json?url=' . rawurlencode($url)
    : 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode($url);
  try {
    $code = $client->get($endpoint, ['timeout' => 15, 'http_errors' => FALSE])->getStatusCode();
  }
  catch (\Exception $e) {
    $code = 0;
  }
  if ($code >= 400 || $code === 0) {
    $dead[] = [$mid, $url, $code];
  }
}
print "dead remote targets: " . count($dead) . " of " . count($urls) . "\n";

// -- Refetch stale generic-icon thumbnails -----------------------------------
// A thumbnail fetched while the URL was still malformed sticks at the
// generic icon even after the URL heals; refetch for every live target
// still showing it.
$dead_mids = array_flip(array_column($dead, 0));
$generic = array_map('intval', $db->query(
  "SELECT t.mid FROM {media_field_data} t
   JOIN {file_managed} f ON f.fid = t.thumbnail__target_id
   WHERE t.mid IN (:mids[]) AND f.uri LIKE :g",
  [':mids[]' => $mids, ':g' => '%media-icons/generic/video.png']
)->fetchCol());
$refetched = 0;
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
foreach ($generic as $mid) {
  if (isset($dead_mids[$mid])) {
    continue;
  }
  $media = $media_storage->load($mid);
  if ($media) {
    $media->updateQueuedThumbnail();
    $media->save();
    $refetched++;
  }
}
print "stale generic thumbnails refetched: $refetched\n";
if ($dead) {
  $csv = '/var/www/html/files/mmi_dead_videos.csv';
  @mkdir(dirname($csv), 0777, TRUE);
  $fh = fopen($csv, 'w');
  fputcsv($fh, ['mid', 'url', 'oembed_status']);
  foreach ($dead as $row) {
    fputcsv($fh, $row);
  }
  fclose($fh);
  print "  -> $csv (triage: unpublish or replace by hand)\n";
}
