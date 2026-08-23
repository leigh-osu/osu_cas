<?php

/**
 * @file
 * Audits block_content entities for orphans — blocks nothing references.
 *
 * Layout Builder inline blocks are content entities that live only through a
 * reference from a layout section. When a section is rebuilt, a host node is
 * deleted, or a migration re-runs, the blocks it pointed at stay behind with
 * nothing pointing to them. They are invisible in the UI (inline blocks have
 * no admin listing) but keep accumulating in block_content.
 *
 * Every way a block_content can be referenced on this site:
 *   1. A layout section on a node or group — either the default (live)
 *      revision or an older/pending one. Old revisions still count: deleting
 *      those blocks would break revision history.
 *   2. field_block_serialized_data.attached_block_ids — container blocks
 *      (picbox grids, menu bars, accordions) hold their children's IDs there.
 *   3. block.block.* config placement, for reusable blocks.
 * There are no entity_reference fields targeting block_content, and no
 * inline blocks in default layouts held in entity_view_display config, so
 * those three channels are exhaustive.
 *
 * Usage:
 *   ddev drush --uri=https://osu-cas.ddev.site scr \
 *     drush/scripts/orphaned_blocks_report.php
 *
 * Writes (untracked, in scripts-dev/):
 *   orphaned_blocks_report.md  — summary by state, bundle and origin
 *   orphaned_blocks.csv        — one row per orphaned block
 */

$md_path = DRUPAL_ROOT . '/../scripts-dev/orphaned_blocks_report.md';
$csv_path = DRUPAL_ROOT . '/../scripts-dev/orphaned_blocks.csv';

$db = \Drupal::database();

/**
 * Pulls every block_revision_id out of a serialized layout section column.
 */
$scan_layouts = function (string $table) use ($db): array {
  $found = [];
  $result = $db->query("SELECT layout_builder__layout_section FROM {" . $table . "}");
  foreach ($result as $record) {
    $blob = $record->layout_builder__layout_section;
    if (!$blob) {
      continue;
    }
    if (preg_match_all('~s:17:"block_revision_id";(?:s:\d+:"(\d+)"|i:(\d+))~', $blob, $m, PREG_SET_ORDER)) {
      foreach ($m as $match) {
        $found[(int) ($match[1] !== '' ? $match[1] : $match[2])] = TRUE;
      }
    }
  }
  return $found;
};

print "scanning layout tables...\n";
$live_revs = $scan_layouts('node__layout_builder__layout')
  + $scan_layouts('group__layout_builder__layout');
$all_revs = $live_revs
  + $scan_layouts('node_revision__layout_builder__layout')
  + $scan_layouts('group_revision__layout_builder__layout');
printf("  block revisions referenced by a live layout: %d\n", count($live_revs));
printf("  block revisions referenced by any revision:  %d\n", count($all_revs));

// Revision IDs -> block IDs.
$rev_to_id = [];
$result = $db->query('SELECT revision_id, id FROM {block_content_revision}');
foreach ($result as $r) {
  $rev_to_id[(int) $r->revision_id] = (int) $r->id;
}
$ids_from = function (array $revs) use ($rev_to_id): array {
  $ids = [];
  foreach (array_keys($revs) as $rev) {
    if (isset($rev_to_id[$rev])) {
      $ids[$rev_to_id[$rev]] = TRUE;
    }
  }
  return $ids;
};
$live_ids = $ids_from($live_revs);
$any_ids = $ids_from($all_revs);

// Channel 2: children named inside a container block's serialized data.
// Some container blocks are deliberately never placed themselves — a picbox
// grid, for instance, is replaced in the layout by one component per child
// card (CasParagraphsLayout::handlePicboxGridLayoutItems()). Those carry the
// grid's settings and are reachable through their children, so they are not
// orphans; they are tracked separately here.
$nested_parent = [];
$container_children = [];
$result = $db->query('SELECT entity_id, field_block_serialized_data_value FROM {block_content__field_block_serialized_data}');
foreach ($result as $r) {
  $data = @unserialize($r->field_block_serialized_data_value);
  $attached = $data['migration']['attached_block_ids'] ?? NULL;
  if (!$attached) {
    continue;
  }
  foreach (explode(',', $attached) as $child) {
    $child = (int) trim($child);
    if ($child) {
      $nested_parent[$child] = (int) $r->entity_id;
      $container_children[(int) $r->entity_id][] = $child;
    }
  }
}
printf("  blocks named as children of a container block: %d\n", count($nested_parent));
printf("  container blocks holding child IDs: %d\n", count($container_children));

// Channel 3: reusable blocks placed through block.block.* config.
$placed_uuids = [];
foreach (\Drupal::configFactory()->listAll('block.block.') as $name) {
  $plugin = \Drupal::config($name)->get('plugin');
  if ($plugin && str_starts_with($plugin, 'block_content:')) {
    $placed_uuids[substr($plugin, strlen('block_content:'))] = $name;
  }
}
printf("  reusable blocks placed in block config: %d\n", count($placed_uuids));

// Origin: which migration produced each block. Selecting map tables by
// "destid1 joins block_content" would be wrong — destination IDs from the
// media, url_alias and taxonomy maps collide numerically with block IDs. Ask
// the migration definitions which ones actually write block_content, and
// match their map tables by name (core truncates map table names to 63
// characters, so match by prefix rather than assuming the full ID).
print "indexing migrate maps...\n";
$origin = [];
$block_migrations = [];
foreach (\Drupal::service('plugin.manager.migration')->getDefinitions() as $mid => $definition) {
  $plugin = $definition['destination']['plugin'] ?? '';
  if ($plugin === 'entity:block_content' || $plugin === 'entity_complete:block_content') {
    $block_migrations[] = strtolower($mid);
  }
}
printf("  migrations whose destination is block_content: %d\n", count($block_migrations));

$tables = $db->query("SHOW TABLES LIKE 'migrate\_map\_%'")->fetchCol();
$matched = 0;
foreach ($tables as $table) {
  $suffix = substr($table, strlen('migrate_map_'));
  $migration = NULL;
  foreach ($block_migrations as $mid) {
    if (str_starts_with($mid, $suffix)) {
      $migration = $mid;
      break;
    }
  }
  if ($migration === NULL) {
    continue;
  }
  $matched++;
  foreach ($db->query('SELECT destid1 FROM {' . $table . '} WHERE destid1 IS NOT NULL') as $r) {
    $origin[(int) $r->destid1] = $migration;
  }
}

// How many of each migration's blocks actually made it into a layout. A
// migration that placed none of them is producing intermediates by design.
$produced_by_migration = [];
$placed_by_migration = [];
foreach ($origin as $block_id => $migration) {
  $produced_by_migration[$migration] = ($produced_by_migration[$migration] ?? 0) + 1;
  if (isset($live_ids[$block_id])) {
    $placed_by_migration[$migration] = ($placed_by_migration[$migration] ?? 0) + 1;
  }
}
printf("  map tables indexed: %d, blocks with a known origin: %d\n", $matched, count($origin));

// Classify every block.
$states = [];
$by_bundle = [];
$orphan_rows = [];
$result = $db->query('SELECT b.id, b.type, b.uuid, d.info, d.reusable
  FROM {block_content} b JOIN {block_content_field_data} d ON d.id = b.id');
foreach ($result as $b) {
  $id = (int) $b->id;
  $live_children = 0;
  foreach ($container_children[$id] ?? [] as $child) {
    if (isset($live_ids[$child])) {
      $live_children++;
    }
  }

  if (isset($live_ids[$id])) {
    $state = 'live layout';
  }
  elseif ($live_children > 0) {
    // Not placed itself, but its child components are — the grid/row settings
    // carrier for a layout that is on the page.
    $state = 'container of live blocks';
  }
  elseif (isset($any_ids[$id])) {
    $state = 'old revision only';
  }
  elseif (isset($nested_parent[$id]) && isset($live_ids[$nested_parent[$id]])) {
    $state = 'child of live container';
  }
  elseif (isset($nested_parent[$id]) && isset($any_ids[$nested_parent[$id]])) {
    $state = 'child of historical container';
  }
  elseif ($b->reusable && isset($placed_uuids[$b->uuid])) {
    $state = 'reusable, placed';
  }
  elseif ($b->reusable) {
    $state = 'reusable, unplaced';
  }
  elseif (isset($nested_parent[$id])) {
    $state = 'ORPHAN (child of orphaned container)';
  }
  else {
    $state = 'ORPHAN';
  }

  $states[$state] = ($states[$state] ?? 0) + 1;
  if (str_starts_with($state, 'ORPHAN')) {
    $by_bundle[$b->type]['orphan'] = ($by_bundle[$b->type]['orphan'] ?? 0) + 1;
    $orphan_rows[] = [
      $id,
      $b->type,
      $b->reusable ? 'reusable' : 'inline',
      $state,
      $origin[$id] ?? '(not from a migration)',
      mb_substr((string) $b->info, 0, 80),
    ];
  }
  $by_bundle[$b->type]['total'] = ($by_bundle[$b->type]['total'] ?? 0) + 1;
}

// Self-check: take a sample of blocks called orphaned and confirm no layout
// blob anywhere mentions any of their revision IDs. A false positive here
// would make the whole report dangerous to act on.
print "\nverifying a sample of orphans against raw layout data...\n";
$sample = array_slice($orphan_rows, 0, 25);
$false_positives = [];
foreach ($sample as $row) {
  $revs = $db->query('SELECT revision_id FROM {block_content_revision} WHERE id = :id', [':id' => $row[0]])->fetchCol();
  foreach ($revs as $rev) {
    foreach (['node__layout_builder__layout', 'node_revision__layout_builder__layout'] as $table) {
      $hit = $db->query('SELECT 1 FROM {' . $table . '}
        WHERE layout_builder__layout_section LIKE :p LIMIT 1',
        [':p' => '%block_revision_id";s:' . strlen((string) $rev) . ':"' . $rev . '"%'])->fetchField();
      if ($hit) {
        $false_positives[] = $row[0] . ' (rev ' . $rev . ' in ' . $table . ')';
      }
    }
  }
}
printf("  sampled %d orphans, false positives: %d%s\n", count($sample), count($false_positives),
  $false_positives ? ' -> ' . implode(', ', $false_positives) : '');

// Orphans grouped by the migration that created them.
$by_origin = [];
foreach ($orphan_rows as $row) {
  $by_origin[$row[4]] = ($by_origin[$row[4]] ?? 0) + 1;
}
arsort($by_origin);
uasort($by_bundle, fn($a, $b) => ($b['orphan'] ?? 0) <=> ($a['orphan'] ?? 0));
arsort($states);

$total = array_sum($states);
$orphans = count($orphan_rows);

$md = [];
$md[] = '# Orphaned block_content audit';
$md[] = '';
$md[] = sprintf('%d block_content entities; **%d (%.1f%%) are orphaned** — nothing on the site references them.',
  $total, $orphans, $total ? $orphans / $total * 100 : 0);
$md[] = '';
$md[] = 'A block is reachable only through a layout section (on a node or group, in';
$md[] = 'the live revision or an older one), through a container block that lists it';
$md[] = 'in field_block_serialized_data, or — for reusable blocks — through a';
$md[] = 'block.block.* placement. Anything else is unreferenced.';
$md[] = '';
$md[] = '## By state';
$md[] = '';
$md[] = '| State | Blocks |';
$md[] = '|---|---:|';
foreach ($states as $state => $count) {
  $md[] = sprintf('| %s | %d |', $state, $count);
}
$md[] = '';
$md[] = '## By bundle';
$md[] = '';
$md[] = '| Bundle | Orphaned | Total | % orphaned |';
$md[] = '|---|---:|---:|---:|';
foreach ($by_bundle as $bundle => $counts) {
  $o = $counts['orphan'] ?? 0;
  $md[] = sprintf('| %s | %d | %d | %.0f%% |', $bundle, $o, $counts['total'], $counts['total'] ? $o / $counts['total'] * 100 : 0);
}
$md[] = '';
$md[] = '## Orphans by originating migration';
$md[] = '';
$md[] = 'A migration with **0 placed** never puts its blocks into a layout at all:';
$md[] = 'the block is an intermediate the layout replaces with its children (an';
$md[] = 'adjustable-columns paragraph becomes one component per column, a menu';
$md[] = 'paragraph becomes osu_menu_bar_item components). Those orphans are';
$md[] = 'architectural leftovers, not lost content. Where a migration places most';
$md[] = 'of its blocks, the orphans are the exceptions worth explaining.';
$md[] = '';
$md[] = '| Migration | Produced | Placed | Orphaned |';
$md[] = '|---|---:|---:|---:|';
foreach ($by_origin as $migration => $count) {
  $produced = $produced_by_migration[$migration] ?? 0;
  $placed = $placed_by_migration[$migration] ?? 0;
  $md[] = sprintf('| %s | %d | %d%s | %d |', $migration, $produced, $placed,
    ($produced && $placed === 0) ? ' **(never placed)**' : '', $count);
}
$md[] = '';
$md[] = sprintf('Per-block detail: `scripts-dev/%s`', basename($csv_path));
$md[] = '';
file_put_contents($md_path, implode("\n", $md));

$fh = fopen($csv_path, 'w');
fputcsv($fh, ['block_id', 'bundle', 'reusable', 'state', 'origin_migration', 'info']);
foreach ($orphan_rows as $row) {
  fputcsv($fh, $row);
}
fclose($fh);

printf("\ntotal blocks: %d | orphaned: %d (%.1f%%)\n", $total, $orphans, $total ? $orphans / $total * 100 : 0);
foreach ($states as $state => $count) {
  printf("  %-38s %d\n", $state, $count);
}
print "\nreport: " . realpath($md_path) . "\n";
print 'detail: ' . realpath($csv_path) . "\n";
