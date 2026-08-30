<?php

/**
 * @file
 * MMI layout hygiene: dead tokens, empty blocks, carrier blocks.
 *
 * The MMI adaptation of the agsci trio (reprocess_media_tokens /
 * prune_empty_layout_blocks / prune_intermediate_blocks), scoped so only
 * MMI content is touched:
 * - Pass A strips raw [[{"fid":...}]] media tokens from MMI node bodies
 *   when the fid has no file_managed row in the MMI D7 database — dead
 *   links BEFORE migration that render as raw JSON (node 405246, fid 471).
 *   Resolvable-looking tokens are left and reported.
 * - Pass B removes empty inline paragraph_block components (and the
 *   sections that empties) from MMI node layouts, keeping anything with a
 *   background/min-height/attribute purpose — same rules as agsci.
 * - Pass C deletes carrier blocks from mmi_* block-producing migrations
 *   that placed none of their blocks (the 34 mmi_paragraph_menu carriers:
 *   layouts place their osu_menu_bar_item children, never the block).
 *   Map rows stay, so a paragraph re-import converges instead of minting
 *   fresh carriers.
 * - Pass D deletes aliases pointing at MMI nodes that never existed — the
 *   19 aliases of un-migrated types (highlights, nav grids, expedition)
 *   from before MmiAliasPath skipped those types in-migration.
 *
 * Must run after the MMI node/layout migrations. Idempotent. Run via
 * mmi_migrate.sh section 10.
 */

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;

$db = Database::getConnection();
$d7 = Database::getConnection('default', 'migrate_mmi');
$storage = \Drupal::entityTypeManager()->getStorage('block_content');

// ---- Pass A: dead-in-D7 media tokens --------------------------------------
$stripped = 0;
$left = [];
$nids = $db->query('SELECT DISTINCT entity_id FROM {node__body} WHERE entity_id >= 400000 AND body_value LIKE :t', [':t' => '%[[{"fid%'])->fetchCol();
foreach ($nids as $nid) {
  $node = Node::load($nid);
  if (!$node || $node->get('body')->isEmpty()) {
    continue;
  }
  $item = $node->get('body')->first()->getValue();
  $new = preg_replace_callback('/\[\[\{"fid":"?(\d+)"?.*?\]\]/s', function ($m) use ($d7, &$stripped) {
    $exists = $d7->query('SELECT 1 FROM {file_managed} WHERE fid = :fid', [':fid' => $m[1]])->fetchField();
    if (!$exists) {
      print "  stripped dead-in-D7 token (fid {$m[1]})\n";
      $stripped++;
      return '';
    }
    return $m[0];
  }, $item['value']);
  if ($new !== $item['value']) {
    $item['value'] = $new;
    $node->get('body')->setValue([$item]);
    $node->setNewRevision(FALSE);
    $node->save();
  }
  if (str_contains($new, '[[{"fid')) {
    preg_match_all('/"fid":"?(\d+)/', $new, $m);
    $left[] = "node $nid (fids " . implode(',', array_unique($m[1])) . ")";
  }
}
print "pass A: $stripped dead tokens stripped\n";
if ($left) {
  print "  still carrying resolvable-looking tokens:\n    " . implode("\n    ", $left) . "\n";
}

// ---- Pass B: empty paragraph blocks in MMI layouts ------------------------
$nids = $db->query("SELECT DISTINCT entity_id FROM {node__layout_builder__layout} WHERE entity_id >= 400000 AND layout_builder__layout_section LIKE '%inline_block:paragraph_block%'")->fetchCol();
$removed_components = $removed_sections = $touched_nodes = 0;
foreach ($nids as $nid) {
  $node = Node::load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    continue;
  }
  $layout = $node->get('layout_builder__layout');
  $changed = FALSE;

  foreach ($layout->getSections() as $section) {
    foreach ($section->getComponents() as $component) {
      if ($component->getPluginId() !== 'inline_block:paragraph_block') {
        continue;
      }
      $styles = $component->get('bootstrap_styles')['block_style'] ?? [];
      if (!empty($styles['background_color']['class'])
        || !empty($styles['background_media']['image']['media_id'])
        || !empty($styles['background_media']['video']['media_id'])
        || !empty($styles['min_height']['class'])) {
        continue;
      }
      $attrs = $component->get('component_attributes')['block_content_attributes']['class'] ?? '';
      if ($attrs !== '') {
        continue;
      }
      $revision_id = $component->get('configuration')['block_revision_id'] ?? NULL;
      if (!$revision_id) {
        continue;
      }
      $block = $storage->loadRevision($revision_id);
      if (!$block) {
        continue;
      }
      $body_empty = $block->get('body')->isEmpty() || trim(strip_tags((string) $block->get('body')->value, '<img><iframe><drupal-media><video><audio><embed><object><hr>')) === '';
      if (!$body_empty || !$block->get('field_eb_background_fc')->isEmpty()) {
        continue;
      }
      $section->removeComponent($component->getUuid());
      $removed_components++;
      $changed = TRUE;
    }
  }

  // Drop sections the pruning emptied, unless they carry their own styling
  // (divider bands) — walk by delta from the end so removals are stable.
  $sections = $layout->getSections();
  for ($delta = count($sections) - 1; $delta >= 0; $delta--) {
    $section = $layout->getSections()[$delta] ?? NULL;
    if (!$section || count($section->getComponents()) > 0) {
      continue;
    }
    $bs = $section->getLayoutSettings()['container_wrapper']['bootstrap_styles'] ?? [];
    if (!empty($bs['background_color']['class'])
      || !empty($bs['background_media'])
      || !empty($bs['min_height']['class'])) {
      continue;
    }
    if ($changed) {
      $layout->removeSection($delta);
      $removed_sections++;
    }
  }

  if ($changed) {
    $node->save();
    $touched_nodes++;
    print "  pruned: $nid ({$node->label()})\n";
  }
}
print "pass B: $removed_components empty blocks removed, $removed_sections emptied sections dropped, $touched_nodes nodes updated\n";

// ---- Pass C: carrier blocks from mmi_* migrations -------------------------
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

// Which mmi_* migrations placed none of the blocks they produced?
$deleted = 0;
$skipped = 0;
foreach (\Drupal::service('plugin.manager.migration')->getDefinitions() as $id => $definition) {
  if (!str_starts_with($id, 'mmi_')) {
    continue;
  }
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
  foreach ($produced as $block_id) {
    if (isset($referenced_ids[$block_id])) {
      $placed++;
    }
  }
  // Reusable blocks live through block.block.* placements, not layouts, so a
  // zero here would mean nothing; skip those migrations entirely.
  $reusable = (int) $db->query('SELECT COUNT(*) FROM {block_content_field_data}
    WHERE id IN (:ids[]) AND reusable = 1', [':ids[]' => $produced])->fetchField();
  if ($placed > 0 || $reusable > 0) {
    continue;
  }
  $to_delete = [];
  foreach ($produced as $block_id) {
    // Re-check each block on its own: a single reachable block must survive
    // even if its siblings do not.
    if (isset($referenced_ids[$block_id]) || isset($child_ids[$block_id])) {
      $skipped++;
      continue;
    }
    $to_delete[] = $block_id;
  }
  // Count entities actually deleted, not candidate IDs: map rows outlive the
  // blocks, so re-runs find nothing left to delete.
  $removed = 0;
  foreach (array_chunk($to_delete, 200) as $chunk) {
    $blocks = $storage->loadMultiple($chunk);
    if ($blocks) {
      $storage->delete($blocks);
      $removed += count($blocks);
    }
  }
  $deleted += $removed;
  printf("  %-40s deleted %d carrier blocks\n", mb_substr($id, 0, 39), $removed);
}
print "pass C: $deleted carrier blocks deleted, $skipped kept as still referenced\n";

// ---- Pass D: aliases pointing at MMI nodes that never existed -------------
$dead_aliases = $db->query("SELECT p.id FROM {path_alias} p
  WHERE p.path REGEXP :r
    AND CAST(SUBSTRING(p.path, 7) AS UNSIGNED) >= 400000
    AND NOT EXISTS (SELECT 1 FROM {node} n WHERE n.nid = CAST(SUBSTRING(p.path, 7) AS UNSIGNED))",
  [':r' => '^/node/[0-9]+$'])->fetchCol();
if ($dead_aliases) {
  $alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
  $alias_storage->delete($alias_storage->loadMultiple($dead_aliases));
}
print "pass D: " . count($dead_aliases) . " aliases to never-migrated nodes deleted\n";
