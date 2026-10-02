<?php

/**
 * @file
 * Backfill inline_block_usage from Layout Builder sections.
 *
 * The D7 migration wrote inline (non-reusable) block_content entities straight
 * into layout sections without recording them in inline_block_usage. Core's
 * SetInlineBlockDependency subscriber uses that table to find the host entity
 * when a block is loaded on its own (media library opener, block revision UI),
 * so every migrated block fails access with "Non-reusable blocks must set an
 * access dependency for access control."
 *
 * Scans the current and historical layout fields of nodes and groups, maps
 * each referenced block revision to its block id, and inserts one usage row
 * per block that has none. Hosts seen in a current revision win over hosts
 * seen only in old revisions; ties go to the lowest entity id and are listed.
 *
 * Every inserted row is also written to a CSV beside this script (or in
 * BACKFILL_LOG_DIR, or the system temp dir if neither is writable) so the
 * backfill can be reversed: DELETE FROM inline_block_usage WHERE
 * block_content_id IN (<ids from the csv>).
 *
 * Usage:
 *   drush --uri=https://osu-cas.ddev.site scr scripts-dev/backfill_inline_block_usage.php          # dry run
 *   drush --uri=https://osu-cas.ddev.site scr scripts-dev/backfill_inline_block_usage.php apply    # write rows + csv
 */

$apply = in_array('apply', $extra ?? [], TRUE);
$db = \Drupal::database();

// [table, entity type, is current revision]
$sources = [
  ['node__layout_builder__layout', 'node', TRUE],
  ['group__layout_builder__layout', 'group', TRUE],
  ['node_revision__layout_builder__layout', 'node', FALSE],
  ['group_revision__layout_builder__layout', 'group', FALSE],
];

// revision_id => ['type' => .., 'id' => .., 'current' => bool]
$hosts = [];
$conflicts = [];
foreach ($sources as [$table, $type, $current]) {
  if (!$db->schema()->tableExists($table)) {
    continue;
  }
  $rows = $db->query("SELECT entity_id, layout_builder__layout_section AS section FROM {{$table}} WHERE layout_builder__layout_section LIKE '%block_revision_id%'");
  $n = 0;
  foreach ($rows as $row) {
    $n++;
    if (!preg_match_all('/s:17:"block_revision_id";(?:i:(\d+)|s:\d+:"(\d+)")/', $row->section, $m)) {
      continue;
    }
    foreach ($m[1] as $k => $v) {
      $rid = (int) ($v !== '' ? $v : $m[2][$k]);
      if (!$rid) {
        continue;
      }
      $candidate = ['type' => $type, 'id' => (int) $row->entity_id, 'current' => $current];
      if (!isset($hosts[$rid])) {
        $hosts[$rid] = $candidate;
        continue;
      }
      $have = $hosts[$rid];
      if ($have['type'] === $type && $have['id'] === $candidate['id']) {
        continue;
      }
      // Current revision beats historical; otherwise keep the lowest id.
      if ($candidate['current'] && !$have['current']) {
        $hosts[$rid] = $candidate;
      }
      if ($candidate['current'] === $have['current']) {
        $conflicts[$rid][] = "{$have['type']}:{$have['id']}";
        $conflicts[$rid][] = "{$type}:{$candidate['id']}";
        if ($candidate['id'] < $have['id']) {
          $hosts[$rid] = $candidate;
        }
      }
    }
  }
  printf("%-42s %6d layout rows scanned\n", $table, $n);
}
printf("distinct block revisions referenced: %d\n", count($hosts));

// revision_id => block id, only for non-reusable blocks.
$rev_to_block = [];
foreach (array_chunk(array_keys($hosts), 2000) as $chunk) {
  $q = $db->query("SELECT r.revision_id, r.id FROM {block_content_revision} r INNER JOIN {block_content_field_data} b ON b.id = r.id WHERE b.reusable = 0 AND r.revision_id IN (:rids[])", [':rids[]' => $chunk]);
  foreach ($q as $r) {
    $rev_to_block[(int) $r->revision_id] = (int) $r->id;
  }
}
$missing_revisions = array_diff_key($hosts, $rev_to_block);
printf("revisions resolving to a non-reusable block: %d (unresolved/reusable: %d)\n", count($rev_to_block), count($missing_revisions));

// block id => host, preferring a current-revision host across the block's revisions.
$block_hosts = [];
foreach ($rev_to_block as $rid => $bid) {
  $h = $hosts[$rid];
  if (!isset($block_hosts[$bid]) || ($h['current'] && !$block_hosts[$bid]['current'])) {
    $block_hosts[$bid] = $h;
  }
}

$existing = $db->query("SELECT block_content_id FROM {inline_block_usage}")->fetchCol();
$existing = array_flip(array_map('intval', $existing));
$to_insert = array_diff_key($block_hosts, $existing);
$total_nonreusable = (int) $db->query("SELECT COUNT(*) FROM {block_content_field_data} WHERE reusable = 0")->fetchField();

printf("non-reusable blocks total: %d\n", $total_nonreusable);
printf("blocks found in layouts: %d (already have usage: %d, to insert: %d)\n", count($block_hosts), count($block_hosts) - count($to_insert), count($to_insert));
printf("non-reusable blocks in no layout at all (left alone): %d\n", $total_nonreusable - count($block_hosts));
$by_type = [];
foreach ($to_insert as $h) {
  $by_type[$h['type']] = ($by_type[$h['type']] ?? 0) + 1;
}
foreach ($by_type as $t => $c) {
  printf("  host type %-6s %d\n", $t, $c);
}
$real_conflicts = array_filter($conflicts, fn($rid) => isset($rev_to_block[$rid]) && isset($to_insert[$rev_to_block[$rid]]), ARRAY_FILTER_USE_KEY);
if ($real_conflicts) {
  printf("revisions referenced by more than one host at the same priority (lowest id chosen): %d\n", count($real_conflicts));
  $shown = 0;
  foreach ($real_conflicts as $rid => $list) {
    printf("  rev %d block %d: %s\n", $rid, $rev_to_block[$rid], implode(', ', array_unique($list)));
    if (++$shown >= 15) {
      echo "  ...\n";
      break;
    }
  }
}

if (!$apply) {
  echo "DRY RUN — pass 'apply' to write rows.\n";
  return;
}

$env = getenv('AH_SITE_ENVIRONMENT') ?: 'local';
$log_dir = getenv('BACKFILL_LOG_DIR') ?: __DIR__;
if (!is_writable($log_dir)) {
  $log_dir = sys_get_temp_dir();
}
$csv_path = sprintf('%s/inline_block_usage_backfill_%s_%s.csv', $log_dir, $env, date('Ymd_His'));
$csv = fopen($csv_path, 'w');
if (!$csv) {
  echo "FAILED: cannot open $csv_path for writing; nothing inserted.\n";
  return;
}
fputcsv($csv, ['block_content_id', 'layout_entity_type', 'layout_entity_id']);

$inserted = 0;
foreach (array_chunk($to_insert, 500, TRUE) as $chunk) {
  $insert = $db->insert('inline_block_usage')->fields(['block_content_id', 'layout_entity_type', 'layout_entity_id']);
  foreach ($chunk as $bid => $h) {
    $insert->values([$bid, $h['type'], (string) $h['id']]);
    fputcsv($csv, [$bid, $h['type'], $h['id']]);
  }
  $inserted += count($chunk);
  $insert->execute();
}
fclose($csv);
printf("inserted %d usage rows; log: %s\n", $inserted, $csv_path);
