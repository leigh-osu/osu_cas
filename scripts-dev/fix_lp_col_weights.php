<?php

/**
 * Phase 2b of the field_lp_col_image repair: correct component weights.
 *
 * Section::appendComponent() overrode the weights set in phase 2, dropping
 * every inserted image column to the end of its section (below the text) and
 * scrambling image order. This pass finds every section containing one of
 * the phase-2 inserted blocks (image-only body, i.e. body is exactly the
 * media embed) and rewrites the weight of EVERY adjcol-mapped component in
 * that section to its true D7 delta — safe for both weight styles the
 * original migration produced (gapped 0,3,4 or compacted 0,1,2), since both
 * orderings agree with delta order.
 *
 * Usage:
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_lp_col_weights.php          (dry run)
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_lp_col_weights.php apply
 */

$apply = isset($extra) && in_array('apply', $extra, TRUE);
$base = __DIR__;

$image_items = [];
foreach (file("$base/.tmp_lp_col_images_d7.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
  [$item_id] = explode("\t", $line);
  $image_items[(int) $item_id] = TRUE;
}
$item_delta = [];
$item_nid = [];
foreach (file("$base/.tmp_lp_adj_columns_d7.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
  [$pid, $delta, $item_id, $nid] = array_pad(explode("\t", $line), 4, '');
  $item_delta[(int) $item_id] = (int) $delta;
  if ($nid !== '') {
    $item_nid[(int) $item_id] = (int) $nid;
  }
}

$db = \Drupal::database();
$node_storage = \Drupal::entityTypeManager()->getStorage('node');

$item_of_block = [];
foreach ($db->query('SELECT sourceid1, destid1 FROM {migrate_map_field_collection_field_lp_adj_column__to__layout_bu}') as $r) {
  $item_of_block[(int) $r->destid1] = (int) $r->sourceid1;
}

// Blocks phase 2 inserted: image items whose block body is exactly the embed.
$inserted_blocks = [];
foreach ($db->query("SELECT b.entity_id, b.body_value FROM {block_content__body} b") as $r) {
  $bid = (int) $r->entity_id;
  if (!isset($item_of_block[$bid]) || !isset($image_items[$item_of_block[$bid]])) {
    continue;
  }
  $body = trim($r->body_value ?? '');
  if ($body !== '' && str_starts_with($body, '<drupal-media') && str_ends_with($body, '</drupal-media>')) {
    $inserted_blocks[$bid] = TRUE;
  }
}
print count($inserted_blocks) . " image-only blocks identified\n";

$nids = array_unique(array_intersect_key($item_nid, $image_items));
foreach ($nids as $nid) {
  $node = $node_storage->load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    continue;
  }
  $changed = FALSE;
  foreach ($node->get('layout_builder__layout')->getSections() as $si => $section) {
    // Resolve every component's block id / item id.
    $mapped = [];
    $has_inserted = FALSE;
    $unmapped = 0;
    foreach ($section->getComponents() as $uuid => $comp) {
      $cfg = $comp->get('configuration');
      if (($cfg['id'] ?? '') !== 'inline_block:paragraph_block' || empty($cfg['block_revision_id'])) {
        continue;
      }
      $bid = (int) $db->query('SELECT id FROM {block_content_revision} WHERE revision_id = :r', [':r' => $cfg['block_revision_id']])->fetchField();
      if (isset($item_of_block[$bid])) {
        $mapped[$uuid] = $item_of_block[$bid];
        if (isset($inserted_blocks[$bid])) {
          $has_inserted = TRUE;
        }
      }
      else {
        $unmapped++;
      }
    }
    if (!$has_inserted) {
      continue;
    }
    if ($unmapped) {
      print "node $nid s$si: WARNING $unmapped unmapped paragraph_block component(s) present, weights left for them\n";
    }
    foreach ($section->getComponents() as $uuid => $comp) {
      if (!isset($mapped[$uuid])) {
        continue;
      }
      $delta = $item_delta[$mapped[$uuid]] ?? NULL;
      if ($delta === NULL) {
        print "node $nid s$si: item {$mapped[$uuid]} has no delta, skipped\n";
        continue;
      }
      if ($comp->getWeight() !== $delta) {
        print ($apply ? 'REWEIGHT' : 'WOULD REWEIGHT') . " node $nid s$si item {$mapped[$uuid]} w=" . $comp->getWeight() . " -> $delta\n";
        $comp->setWeight($delta);
        $changed = TRUE;
      }
    }
  }
  if ($apply && $changed) {
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    print "node $nid saved\n";
  }
}
print "done (" . ($apply ? 'APPLY' : 'DRY RUN') . ")\n";
