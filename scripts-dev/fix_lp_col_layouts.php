<?php

/**
 * Phase 2 of the field_lp_col_image repair: place the recovered image-only
 * adjustable-columns blocks back into their nodes' Layout Builder layouts.
 *
 * The image-only column blocks were recreated by a later migration run, so
 * they exist (and phase 1, fix_lp_col_images.php, gave them their media
 * embed bodies) but the node layouts were built while they were missing and
 * never rebuilt. This script finds each affected block's section — via the
 * sibling columns of the same D7 paragraph that DID get placed — and inserts
 * a component with the same region, weight = D7 delta, and column classes
 * rebuilt from the block's own serialized migration data.
 *
 * Usage:
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_lp_col_layouts.php          (dry run)
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_lp_col_layouts.php apply
 *
 * Inputs:
 *   scripts-dev/.tmp_lp_col_images_d7.tsv   item_id, fid, alt, title
 *   scripts-dev/.tmp_lp_adj_columns_d7.tsv  paragraph_id, delta, item_id, nid
 */

use Drupal\layout_builder\SectionComponent;

$apply = isset($extra) && in_array('apply', $extra, TRUE);
$base = __DIR__;

$image_items = [];
foreach (file("$base/.tmp_lp_col_images_d7.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
  [$item_id, $fid] = array_pad(explode("\t", $line), 2, '');
  $image_items[(int) $item_id] = (int) $fid;
}

// paragraph_id => [delta => item_id], item_id => [paragraph, delta, nid]
$para_items = [];
$item_info = [];
foreach (file("$base/.tmp_lp_adj_columns_d7.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
  [$pid, $delta, $item_id, $nid] = array_pad(explode("\t", $line), 4, '');
  $para_items[(int) $pid][(int) $delta] = (int) $item_id;
  $item_info[(int) $item_id] = ['paragraph' => (int) $pid, 'delta' => (int) $delta, 'nid' => (int) $nid];
}

$db = \Drupal::database();
$block_storage = \Drupal::entityTypeManager()->getStorage('block_content');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$uuid_service = \Drupal::service('uuid');

$block_of_item = function (int $item_id) use ($db) {
  return (int) $db->query('SELECT destid1 FROM {migrate_map_field_collection_field_lp_adj_column__to__layout_bu} WHERE sourceid1 = :s', [':s' => $item_id])->fetchField();
};

$stats = ['image_items' => 0, 'no_block' => 0, 'no_node' => 0, 'placed_already' => 0, 'inserted' => 0, 'manual' => 0];
$manual = [];

// Group affected items by node so each node is loaded and saved once.
$by_node = [];
foreach ($image_items as $item_id => $fid) {
  if (!isset($item_info[$item_id])) {
    continue; // archived orphan, not on any node
  }
  $nid = $item_info[$item_id]['nid'];
  if ($nid) {
    $by_node[$nid][] = $item_id;
  }
}

foreach ($by_node as $nid => $items) {
  $node = $node_storage->load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    $stats['no_node'] += count($items);
    $manual[] = "node $nid missing/no layout (items " . implode(',', $items) . ")";
    continue;
  }

  $sections = $node->get('layout_builder__layout')->getSections();

  // Map: block_content id => [section index, region, weight] for placed blocks.
  $placed = [];
  foreach ($sections as $si => $section) {
    foreach ($section->getComponents() as $component) {
      $cfg = $component->get('configuration');
      if (empty($cfg['block_revision_id'])) {
        continue;
      }
      $bid = (int) $db->query('SELECT id FROM {block_content_revision} WHERE revision_id = :r', [':r' => $cfg['block_revision_id']])->fetchField();
      if ($bid) {
        $placed[$bid] = ['section' => $si, 'region' => $component->getRegion(), 'weight' => $component->getWeight()];
      }
    }
  }

  $changed = FALSE;
  foreach ($items as $item_id) {
    $stats['image_items']++;
    $block_id = $block_of_item($item_id);
    if (!$block_id) {
      $stats['no_block']++;
      continue;
    }
    if (isset($placed[$block_id])) {
      $stats['placed_already']++;
      continue;
    }

    // Locate the target section via a placed sibling column.
    $target = NULL;
    $pid = $item_info[$item_id]['paragraph'];
    foreach ($para_items[$pid] as $sib_item) {
      if ($sib_item === $item_id) {
        continue;
      }
      $sib_block = $block_of_item($sib_item);
      if ($sib_block && isset($placed[$sib_block])) {
        $target = $placed[$sib_block];
        break;
      }
    }
    if ($target === NULL) {
      $stats['manual']++;
      $manual[] = "node $nid item $item_id block $block_id: no placed sibling (paragraph $pid) — needs manual placement";
      continue;
    }

    $block = $block_storage->load($block_id);
    $revision_id = $block_storage->getLatestRevisionId($block_id);

    // Rebuild column classes/styles from the block's serialized D7 data,
    // mirroring CasLayoutBase::handleAdjustableColumnsItems().
    $extra_raw = $block->get('field_block_serialized_data')->value;
    $col = [];
    if ($extra_raw) {
      $un = unserialize($extra_raw);
      $col = $un['migration']['adjustable_columns_item'] ?? [];
    }
    $classes = [];
    $styles = [];
    foreach (['xs', 'sm', 'md', 'lg'] as $bp) {
      if (isset($col["field_lp_col_{$bp}_width"][0]['value'])) {
        $classes[] = "col-$bp-" . $col["field_lp_col_{$bp}_width"][0]['value'];
      }
      if (isset($col["field_lp_col_{$bp}_offset"][0]['value'])) {
        $classes[] = "offset-$bp-" . $col["field_lp_col_{$bp}_offset"][0]['value'];
      }
    }
    if (isset($col['field_lp_col_padding'][0]['value'])) {
      $v = $col['field_lp_col_padding'][0]['value'];
      $styles[] = str_contains($v, '{') ? $v : "padding: $v;";
    }
    foreach ($col['field_lp_col_class'] ?? [] as $v) {
      if (str_contains($v['value'], '{')) {
        $styles[] = $v['value'];
      }
      else {
        $classes[] = $v['value'];
      }
    }
    foreach ($col['field_lp_col_style'] ?? [] as $v) {
      $styles[] = $v['value'];
    }

    $additional = [];
    foreach (['block_attributes', 'block_title_attributes', 'block_content_attributes'] as $element) {
      $additional['component_attributes'][$element] = ['id' => '', 'class' => '', 'style' => '', 'data' => ''];
    }
    $additional['component_attributes']['block_attributes']['class'] = implode(' ', $classes);
    $additional['component_attributes']['block_attributes']['style'] = implode(' ', $styles);
    if (isset($col['field_lp_col_bg_color'][0]['value']) && class_exists('Drupal\osu_migrations_cas\CasLayoutBase')) {
      $bg = \Drupal\osu_migrations_cas\CasLayoutBase::mapLarchBackgroundClass($col['field_lp_col_bg_color'][0]['value']);
      if ($bg) {
        $additional['bootstrap_styles']['block_style']['background']['background_type'] = 'color';
        $additional['bootstrap_styles']['block_style']['background_color']['class'] = $bg;
      }
    }

    $component = SectionComponent::fromArray([
      'uuid' => $uuid_service->generate(),
      'region' => $target['region'],
      'configuration' => [
        'id' => 'inline_block:paragraph_block',
        'label' => 'Layout Builder Inline Block',
        'provider' => 'layout_builder',
        'label_display' => '0',
        'view_mode' => 'full',
        'block_revision_id' => $revision_id,
        'block_serialized' => NULL,
        'context_mapping' => [],
      ],
      'additional' => $additional,
      'weight' => $item_info[$item_id]['delta'],
    ]);

    if ($apply) {
      $sections[$target['section']]->appendComponent($component);
    }
    $changed = TRUE;
    $stats['inserted']++;
    print ($apply ? 'INSERTED' : 'WOULD INSERT') . " node $nid section {$target['section']} region {$target['region']} weight {$item_info[$item_id]['delta']} block $block_id rev $revision_id classes=\"" . implode(' ', $classes) . "\"\n";
  }

  if ($apply && $changed) {
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
  }
}

print "\n=== " . ($apply ? 'APPLY' : 'DRY RUN') . " ===\n";
foreach ($stats as $k => $v) {
  print "$k: $v\n";
}
if ($manual) {
  print "\nManual attention:\n" . implode("\n", $manual) . "\n";
}
