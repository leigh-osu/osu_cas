<?php

/**
 * @file
 * Delete migrated container blocks that no layout ever references.
 *
 * Some paragraph -> Layout Builder migrations produce a block that is only a
 * carrier: CasParagraphsLayout reads it while building the layout and then
 * places its *children* as the components, never the block itself. An
 * lp_adjustable_columns paragraph becomes one component per column; a menu
 * paragraph becomes osu_menu_bar_item components. The carrier block is left
 * behind, unreachable from any layout and invisible in the UI (inline blocks
 * have no admin listing), so it just accumulates — 4,986 of them on the
 * current rebuild, half of all orphaned block_content.
 *
 * Which migrations those are is worked out from the data rather than
 * hardcoded: any block-producing migration that placed *none* of its blocks
 * is producing carriers by design. Every block is then checked individually
 * before deletion — it must be non-reusable, absent from every layout section
 * (live and revision, nodes and groups), and not named as the child of any
 * container block. Nothing that a page can still reach is touched.
 *
 * Migrate map rows are deliberately left in place: they keep `drush ms`
 * honest and make a later re-import of the paragraph migration a no-op rather
 * than a source of 4,986 fresh carriers. A node/layout migration re-run after
 * this has run needs its paragraph migration re-run first — the carriers it
 * reads are gone, and CasLayoutBase::handleMissingBlockException() logs the
 * miss rather than failing the row.
 *
 * Must run after all node/group layout migrations. Idempotent; runs in
 * rebuild section 7.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/prune_intermediate_blocks.php
 */

use Drupal\Core\Database\Database;

$db = Database::getConnection();
$storage = \Drupal::entityTypeManager()->getStorage('block_content');

// Every block revision any layout section points at, live or historical.
$referenced_revisions = [];
foreach ([
  'node__layout_builder__layout',
  'node_revision__layout_builder__layout',
  'group__layout_builder__layout',
  'group_revision__layout_builder__layout',
] as $table) {
  if (!$db->schema()->tableExists($table)) {
    continue;
  }
  foreach ($db->query('SELECT layout_builder__layout_section FROM {' . $table . '}') as $record) {
    $blob = $record->layout_builder__layout_section;
    if ($blob && preg_match_all('~s:17:"block_revision_id";(?:s:\d+:"(\d+)"|i:(\d+))~', $blob, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $referenced_revisions[(int) ($match[1] !== '' ? $match[1] : $match[2])] = TRUE;
      }
    }
  }
}
$referenced_ids = [];
foreach ($db->query('SELECT revision_id, id FROM {block_content_revision}') as $record) {
  if (isset($referenced_revisions[(int) $record->revision_id])) {
    $referenced_ids[(int) $record->id] = TRUE;
  }
}
print count($referenced_ids) . " blocks are referenced by a layout\n";

// Blocks named as a child inside a container block's serialized data.
$child_ids = [];
foreach ($db->query('SELECT field_block_serialized_data_value FROM {block_content__field_block_serialized_data}') as $record) {
  $data = @unserialize($record->field_block_serialized_data_value);
  foreach (explode(',', (string) ($data['migration']['attached_block_ids'] ?? '')) as $child) {
    $child = (int) trim($child);
    if ($child) {
      $child_ids[$child] = TRUE;
    }
  }
}

// Which migrations placed none of the blocks they produced?
$carrier_migrations = [];
foreach (\Drupal::service('plugin.manager.migration')->getDefinitions() as $id => $definition) {
  $plugin = $definition['destination']['plugin'] ?? '';
  if ($plugin !== 'entity:block_content' && $plugin !== 'entity_complete:block_content') {
    continue;
  }
  $map_table = substr('migrate_map_' . strtolower($id), 0, 63);
  if (!$db->schema()->tableExists($map_table)) {
    continue;
  }
  $produced = array_map('intval',
    $db->query('SELECT destid1 FROM {' . $map_table . '} WHERE destid1 IS NOT NULL')->fetchCol());
  if (!$produced) {
    continue;
  }
  $placed = 0;
  $reusable = (int) $db->query('SELECT COUNT(*) FROM {block_content_field_data}
    WHERE id IN (:ids[]) AND reusable = 1', [':ids[]' => $produced])->fetchField();
  foreach ($produced as $block_id) {
    if (isset($referenced_ids[$block_id])) {
      $placed++;
    }
  }
  // Reusable blocks live through block.block.* placements, not layouts, so a
  // zero here would mean nothing; skip those migrations entirely.
  if ($placed === 0 && $reusable === 0) {
    $carrier_migrations[$id] = $produced;
  }
}

if (!$carrier_migrations) {
  print "No carrier-only migrations found; nothing to prune.\n";
  return;
}

$deleted = 0;
$skipped = 0;
foreach ($carrier_migrations as $migration => $produced) {
  $to_delete = [];
  foreach ($produced as $block_id) {
    // Re-check each block on its own rather than trusting the migration-level
    // verdict: a single reachable block must survive even if its siblings do
    // not.
    if (isset($referenced_ids[$block_id]) || isset($child_ids[$block_id])) {
      $skipped++;
      continue;
    }
    $to_delete[] = $block_id;
  }
  if (!$to_delete) {
    printf("  %-58s nothing to delete\n", mb_substr($migration, 0, 57));
    continue;
  }
  // Count entities actually loaded and deleted, not candidate IDs: map rows
  // outlive the blocks, so on a second run every ID is still a candidate but
  // nothing remains to delete.
  $removed = 0;
  foreach (array_chunk($to_delete, 200) as $chunk) {
    $blocks = $storage->loadMultiple($chunk);
    if ($blocks) {
      $storage->delete($blocks);
      $removed += count($blocks);
    }
  }
  $deleted += $removed;
  printf("  %-58s deleted %d\n", mb_substr($migration, 0, 57), $removed);
}

printf("\ncarrier-only migrations: %d | blocks deleted: %d | kept as still referenced: %d\n",
  count($carrier_migrations), $deleted, $skipped);
