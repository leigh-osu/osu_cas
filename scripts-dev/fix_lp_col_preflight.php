<?php

/**
 * Pre-flight audit for the field_lp_col_image repair: find recent author
 * activity on the entities the repair will touch, BEFORE applying anything.
 * Read-only; run it first on each environment and eyeball every flagged row.
 *
 * Flags, per affected node (derived from the TSVs + migrate maps, same as
 * the repair scripts):
 *   - a current revision authored within the window (default 10 days) —
 *     an editor touched the page recently; check the repair's dry-run
 *     output for that node against what they changed;
 *   - a pending revision newer than the default (a draft) — the repair's
 *     layout changes land on the default revision only, so publishing the
 *     draft would silently drop them;
 *   - an affected block whose body changed within the window, or that
 *     already starts with a drupal-media embed (someone re-added the image
 *     by hand; phase 1 skips it, but the layout phases should be checked).
 *
 * Usage:
 *   drush scr fix_lp_col_preflight.php            (10-day window)
 *   drush scr fix_lp_col_preflight.php 21         (21-day window)
 */

$days = isset($extra[0]) && ctype_digit($extra[0]) ? (int) $extra[0] : 10;
$cutoff = time() - $days * 86400;
$base = __DIR__;

$image_items = [];
foreach (file("$base/.tmp_lp_col_images_d7.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
  [$item_id] = explode("\t", $line);
  $image_items[(int) $item_id] = TRUE;
}
$item_nid = [];
foreach (file("$base/.tmp_lp_adj_columns_d7.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
  [$pid, $delta, $item_id, $nid] = array_pad(explode("\t", $line), 4, '');
  if ($nid !== '' && isset($image_items[(int) $item_id])) {
    $item_nid[(int) $item_id] = (int) $nid;
  }
}
$nids = array_values(array_unique($item_nid));

$db = \Drupal::database();

$block_ids = [];
foreach (array_keys($image_items) as $item_id) {
  $bid = $db->query('SELECT destid1 FROM {migrate_map_field_collection_field_lp_adj_column__to__layout_bu} WHERE sourceid1 = :s', [':s' => $item_id])->fetchField();
  if ($bid) {
    $block_ids[] = (int) $bid;
  }
}

print "Pre-flight audit: " . count($nids) . " nodes, " . count($block_ids) . " blocks, window " . $days . " days (since " . date('Y-m-d H:i', $cutoff) . ")\n\n";
$flags = 0;

// Nodes: recent author revisions and pending drafts.
foreach ($db->query('
  SELECT n.nid, n.vid, n.title, r.revision_timestamp, u.name,
    (SELECT MAX(r2.vid) FROM {node_revision} r2 WHERE r2.nid = n.nid) AS max_vid
  FROM {node_field_data} n
  JOIN {node_revision} r ON r.vid = n.vid
  LEFT JOIN {users_field_data} u ON u.uid = r.revision_uid
  WHERE n.nid IN (:nids[])', [':nids[]' => $nids]) as $row) {
  if ((int) $row->revision_timestamp >= $cutoff) {
    $flags++;
    print "FLAG node {$row->nid} \"{$row->title}\": current revision authored " . date('Y-m-d H:i', $row->revision_timestamp) . " by {$row->name} — review dry-run output for this node\n";
  }
  if ((int) $row->max_vid > (int) $row->vid) {
    $flags++;
    print "FLAG node {$row->nid} \"{$row->title}\": pending draft (vid {$row->vid} < latest {$row->max_vid}) — layout changes on the default revision would be lost when the draft publishes\n";
  }
}

// Blocks: recent body edits, or an embed already present at the top.
// The migration import stamped thousands of blocks with the same changed
// value; any timestamp shared by 50+ migrated adjcol blocks is import
// noise, not an author, and is not flagged.
$mass_timestamps = $db->query('
  SELECT changed FROM {block_content_field_data}
  WHERE info = :info GROUP BY changed HAVING COUNT(*) >= 50',
  [':info' => 'Migrated d7 lp_adjustable_columns item'])->fetchCol();
$mass_timestamps = array_map('intval', $mass_timestamps);
foreach ($db->query('
  SELECT bc.id, bc.changed, b.body_value
  FROM {block_content_field_data} bc
  LEFT JOIN {block_content__body} b ON b.entity_id = bc.id
  WHERE bc.id IN (:ids[])', [':ids[]' => $block_ids]) as $row) {
  if ((int) $row->changed >= $cutoff && !in_array((int) $row->changed, $mass_timestamps, TRUE)) {
    $flags++;
    print "FLAG block {$row->id}: changed " . date('Y-m-d H:i', $row->changed) . " — an editor touched this block recently\n";
  }
  $body = $row->body_value !== NULL ? ltrim($row->body_value) : '';
  if (str_starts_with($body, '<drupal-media')) {
    // Our own phase-1 embeds carry data-view-mode="column_image"; anything
    // else at the top of the body was put there by hand.
    $first_tag = substr($body, 0, strpos($body, '>') ?: 0);
    if (!str_contains($first_tag, 'data-view-mode="column_image"')) {
      $flags++;
      print "NOTE block {$row->id}: body already starts with a media embed not written by this repair (phase 1 skips same-image bodies; verify if different)\n";
    }
  }
}

print "\n" . ($flags ? "$flags flag(s) — review each before applying." : "Clean: no recent author activity on any affected node or block.") . "\n";
