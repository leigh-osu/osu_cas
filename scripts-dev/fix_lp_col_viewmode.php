<?php

/**
 * Phase 1b of the field_lp_col_image repair: render the restored column
 * images through the new column_image view mode (1200x1600 focal-point
 * scale-and-crop, the D7 larch_column_image equivalent) instead of the
 * frameless default, by adding data-view-mode to the embeds phase 1 wrote.
 * Only the embed at the start of the body (ours) is touched.
 *
 * Usage:
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_lp_col_viewmode.php          (dry run)
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_lp_col_viewmode.php apply
 */

$apply = isset($extra) && in_array('apply', $extra, TRUE);
$base = __DIR__;

$db = \Drupal::database();
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

$stats = ['blocks' => 0, 'rows_updated' => 0, 'no_change' => 0];

foreach (file("$base/.tmp_lp_col_images_d7.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
  [$item_id, $fid] = array_pad(explode("\t", $line), 2, '');
  $block_id = $db->query('SELECT destid1 FROM {migrate_map_field_collection_field_lp_adj_column__to__layout_bu} WHERE sourceid1 = :s', [':s' => $item_id])->fetchField();
  if (!$block_id) {
    continue;
  }
  $media_id = $db->query('SELECT destid1 FROM {migrate_map_upgrade_d7_media_images} WHERE sourceid1 = :s', [':s' => $fid])->fetchField();
  $media = $media_id ? $media_storage->load($media_id) : NULL;
  if (!$media) {
    continue;
  }
  $needle = '<drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '"';
  $replacement = $needle . ' data-view-mode="column_image"';

  $touched = FALSE;
  foreach (['block_content__body', 'block_content_revision__body'] as $table) {
    $rows = $db->query("SELECT revision_id, body_value FROM {" . $table . "} WHERE entity_id = :id", [':id' => $block_id]);
    foreach ($rows as $row) {
      if (!str_starts_with($row->body_value ?? '', $needle)) {
        continue;
      }
      if (str_starts_with($row->body_value, $needle . ' data-view-mode=')) {
        continue;
      }
      $touched = TRUE;
      if ($apply) {
        $db->update($table)
          ->fields(['body_value' => $replacement . substr($row->body_value, strlen($needle))])
          ->condition('entity_id', $block_id)
          ->condition('revision_id', $row->revision_id)
          ->execute();
      }
      $stats['rows_updated']++;
    }
  }
  if ($touched) {
    $stats['blocks']++;
    if ($apply) {
      \Drupal\Core\Cache\Cache::invalidateTags(['block_content:' . $block_id]);
    }
  }
  else {
    $stats['no_change']++;
  }
}

print ($apply ? 'APPLY' : 'DRY RUN') . ': ' . json_encode($stats) . "\n";
