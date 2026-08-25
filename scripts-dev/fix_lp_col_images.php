<?php

/**
 * Repair: D7 field_lp_col_image never migrated (CasParagraphImage pseudo-field
 * bug). Prepend a <drupal-media> embed to each migrated adjustable-columns
 * block body, on every revision, using the existing migrate maps.
 *
 * Usage:
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_lp_col_images.php            (dry run)
 *   APPLY=1 ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_lp_col_images.php    (apply)
 *
 * Input: scripts-dev/.tmp_lp_col_images_d7.tsv  (item_id, fid, alt, title)
 */

$apply = isset($extra) && in_array('apply', $extra, TRUE);
$tsv = __DIR__ . '/.tmp_lp_col_images_d7.tsv';
if (!is_readable($tsv)) {
  print "Cannot read $tsv\n";
  return;
}

$db = \Drupal::database();
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

$stats = ['rows' => 0, 'no_block_map' => 0, 'no_media_map' => 0, 'no_media_entity' => 0, 'already' => 0, 'updated' => 0];
$missing = [];

foreach (file($tsv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
  $stats['rows']++;
  [$item_id, $fid, $alt, $title] = array_pad(explode("\t", $line), 4, '');

  $block_id = $db->query('SELECT destid1 FROM {migrate_map_field_collection_field_lp_adj_column__to__layout_bu} WHERE sourceid1 = :s', [':s' => $item_id])->fetchField();
  if (!$block_id) {
    $stats['no_block_map']++;
    $missing[] = "item $item_id (fid $fid): no block mapping";
    continue;
  }

  $media_id = $db->query('SELECT destid1 FROM {migrate_map_upgrade_d7_media_images} WHERE sourceid1 = :s', [':s' => $fid])->fetchField();
  if (!$media_id) {
    $stats['no_media_map']++;
    $missing[] = "item $item_id (fid $fid, block $block_id): no media mapping";
    continue;
  }
  $media = $media_storage->load($media_id);
  if (!$media) {
    $stats['no_media_entity']++;
    $missing[] = "item $item_id (fid $fid, block $block_id): media $media_id gone";
    continue;
  }
  $uuid = $media->uuid();

  $embed = '<drupal-media data-entity-type="media" data-entity-uuid="' . $uuid . '"';
  if ($alt !== '') {
    $embed .= ' alt="' . htmlspecialchars($alt, ENT_QUOTES) . '"';
  }
  $embed .= "></drupal-media>\n";

  // Idempotency: if any body row of this block already references the media, skip.
  $existing = $db->query('SELECT COUNT(*) FROM {block_content_revision__body} WHERE entity_id = :id AND body_value LIKE :uuid', [':id' => $block_id, ':uuid' => '%' . $uuid . '%'])->fetchField();
  if ($existing) {
    $stats['already']++;
    continue;
  }

  $current_vid = $db->query('SELECT revision_id FROM {block_content} WHERE id = :id', [':id' => $block_id])->fetchField();
  $vids = $db->query('SELECT revision_id FROM {block_content_revision} WHERE id = :id', [':id' => $block_id])->fetchCol();

  if ($apply) {
    foreach ($vids as $vid) {
      upsert_body($db, 'block_content_revision__body', $block_id, $vid, $embed);
    }
    upsert_body($db, 'block_content__body', $block_id, $current_vid, $embed);
    \Drupal\Core\Cache\Cache::invalidateTags(['block_content:' . $block_id]);
  }
  $stats['updated']++;
  print ($apply ? 'UPDATED' : 'WOULD UPDATE') . " block $block_id (item $item_id, fid $fid -> media $media_id, " . count($vids) . " revision(s))\n";
}

function upsert_body($db, $table, $block_id, $vid, $embed) {
  $row = $db->query("SELECT body_value, body_format FROM {" . $table . "} WHERE entity_id = :id AND revision_id = :vid", [':id' => $block_id, ':vid' => $vid])->fetchAssoc();
  if ($row) {
    $db->update($table)
      ->fields(['body_value' => $embed . $row['body_value']])
      ->condition('entity_id', $block_id)
      ->condition('revision_id', $vid)
      ->execute();
  }
  else {
    $db->insert($table)
      ->fields([
        'bundle' => 'paragraph_block',
        'deleted' => 0,
        'entity_id' => $block_id,
        'revision_id' => $vid,
        'langcode' => 'en',
        'delta' => 0,
        'body_value' => $embed,
        'body_summary' => NULL,
        'body_format' => 'full_html',
      ])
      ->execute();
  }
}

print "\n=== " . ($apply ? 'APPLY' : 'DRY RUN') . " ===\n";
foreach ($stats as $k => $v) {
  print "$k: $v\n";
}
if ($missing) {
  print "\nUnmappable rows:\n" . implode("\n", $missing) . "\n";
}
