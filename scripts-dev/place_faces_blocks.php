<?php

/**
 * @file
 * Place cas_group_faces blocks where D7 embedded faces_of_agsci.
 *
 * Every live embed was one of three variants: 1 or 2 random Student cards
 * scoped to the page's group, or 2 Student+CDI cards drawn from every group.
 * Same provenance machinery as the profiles/news placements; existing
 * cas_group_faces components are cleared per node first, so reruns fully
 * reconcile. Generated from the D7 view export + embed audit.
 *
 * Usage: drush scr scripts-dev/place_faces_blocks.php
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

$display_map = [
  'block_2' => ['items' => 2, 'terms' => [5], 'all_groups' => FALSE],
  'block_3' => ['items' => 1, 'terms' => [5], 'all_groups' => FALSE],
  'block_4' => ['items' => 2, 'terms' => [5, 19656], 'all_groups' => TRUE],
];

// [node nid, D7 section delta, D7 column delta (-1 = non-column embed),
//  D7 column item id (0 = n/a), D7 display id].
$placements = [
  [230631, 3, 0, 49241, 'block_2'],
  [231201, 4, 0, 124651, 'block_2'],
  [232121, 7, 0, 44561, 'block_2'],
  [238231, 6, 1, 31151, 'block_3'],
  [238806, 2, 0, 105051, 'block_2'],
  [241511, 6, 0, 38721, 'block_2'],
  [241596, 6, 3, 38561, 'block_3'],
  [243131, 5, 0, 44571, 'block_2'],
  [245181, 3, 0, 46021, 'block_2'],
  [246476, 8, 0, 49001, 'block_2'],
  [247551, 10, 0, 48991, 'block_2'],
  [247581, 10, 0, 49071, 'block_2'],
  [247691, 2, 1, 49331, 'block_3'],
  [248286, 6, 0, 63156, 'block_2'],
  [249786, 3, 0, 59181, 'block_3'],
  [261626, 4, 0, 82501, 'block_2'],
  [263371, 7, 0, 86986, 'block_2'],
  [269321, 0, 0, 99201, 'block_3'],
  [284306, 7, 0, 130221, 'block_4'],
  [287511, 7, 0, 137871, 'block_2'],
  [287591, 6, 0, 137621, 'block_3'],
];

$db = \Drupal::database();
$col_map = $db->query('SELECT destid1, sourceid1 FROM {migrate_map_field_collection_field_lp_adj_column__to__layout_bu} WHERE destid1 IS NOT NULL')->fetchAllKeyed();

// D7 paragraph structure of nodes with paragraph-hosted embeds: nid =>
// [[delta, item id], ...] (column items listed at their parent's delta).
$para_structure = [
  215 => [[0, 92236], [1, 2806], [2, 2816], [3, 117861], [4, 117376], [5, 117386], [6, 117391], [7, 119676], [8, 117416], [9, 35946], [10, 74201]],
  16132 => [[0, 46066]],
  16845 => [[0, 2171], [1, 2181], [2, 110036], [3, 25076], [4, 25086], [5, 25091], [6, 74456], [7, 68271], [8, 25106]],
  24266 => [[0, 74141], [1, 74151], [2, 74156], [3, 74171]],
  38526 => [[0, 132646], [1, 132631], [2, 132651], [3, 132636], [4, 132656], [5, 132641]],
  46176 => [[0, 13091], [1, 13096], [2, 13101], [3, 73856]],
  87331 => [[0, 8286], [1, 116736], [2, 8296], [3, 130331], [4, 132746], [5, 130686], [6, 132756], [7, 138191], [8, 138196], [9, 25646], [10, 132751], [11, 116721], [12, 117521], [13, 74806], [14, 116726]],
  109801 => [[0, 40421]],
  109811 => [[0, 100516], [0, 106341], [1, 34941], [2, 24226], [3, 34841], [4, 53481]],
  113066 => [[0, 92566], [0, 100856], [1, 100861], [2, 60981], [3, 14096], [4, 25956], [5, 52486], [6, 13736], [7, 13396], [8, 13646], [9, 13651], [10, 37341], [11, 22261]],
  167221 => [[0, 19121], [1, 19126], [2, 19131], [3, 19136], [4, 29626], [5, 28886], [6, 19141]],
  213586 => [[0, 26476], [1, 129706], [2, 129701]],
  230226 => [[0, 47846]],
  230796 => [[0, 18676], [0, 48691], [1, 48696]],
  242761 => [[0, 65281]],
  242866 => [[0, 65321], [1, 65326], [2, 65331], [3, 65336], [4, 65341]],
  264696 => [[0, 98326]],
  265681 => [[0, 99526], [1, 99606], [2, 99586], [3, 99611]],
  283166 => [[0, 127726], [1, 127731], [2, 127736], [3, 127741], [4, 127746], [5, 127751], [6, 127756], [7, 127761], [8, 127766], [9, 127771], [10, 127776], [11, 127781], [12, 127786], [13, 127796]],
];

// Any migrated inline block -> its D7 source item, across every paragraph
// migration (the viewfield/2_column_views bundles were skipped, which is
// exactly why delta arithmetic cannot find their sections).
$para_map = [];
$map_tables = $db->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND TABLE_NAME LIKE 'migrate\_map\_paragraph\_%'")->fetchCol();
foreach ($map_tables as $table) {
  foreach ($db->query("SELECT destid1, sourceid1 FROM {" . $table . "} WHERE destid1 IS NOT NULL") as $row) {
    $para_map[$row->destid1] = (int) $row->sourceid1;
  }
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$uuid = \Drupal::service('uuid');

$by_node = [];
foreach ($placements as [$nid, $delta, $col, $col_item, $display]) {
  $by_node[$nid][] = [$delta, $col, $col_item, $display];
}

$placed = $missing_node = $inserted_sections = 0;
foreach ($by_node as $nid => $items) {
  $node = $storage->load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    print "SKIP $nid: no node/layout\n";
    $missing_node++;
    continue;
  }
  $list = $node->get('layout_builder__layout');

  // Reconcile from scratch: drop every existing people listing, then any
  // now-empty section this script created on a previous run (label 'people').
  foreach ($list->getSections() as $section) {
    foreach ($section->getComponents() as $c) {
      if (($c->get('configuration')['id'] ?? '') === 'cas_group_faces') {
        $section->removeComponent($c->getUuid());
      }
    }
  }
  for ($si = count($list->getSections()) - 1; $si >= 0; $si--) {
    $section = $list->getSections()[$si];
    if (($section->getLayoutSettings()['label'] ?? '') === 'faces' && !$section->getComponents()) {
      $list->removeSection($si);
    }
  }

  // Column item id -> section index, via the inline blocks each section holds.
  $item_section = [];
  foreach ($list->getSections() as $si => $section) {
    foreach ($section->getComponents() as $c) {
      $cfg = $c->get('configuration');
      if (str_starts_with($cfg['id'] ?? '', 'inline_block:') && !empty($cfg['block_revision_id'])) {
        $bid = $db->query('SELECT id FROM {block_content_revision} WHERE revision_id = :r', [':r' => $cfg['block_revision_id']])->fetchField();
        if ($bid && isset($col_map[$bid])) {
          $item_section[(int) $col_map[$bid]] = $si;
        }
        if ($bid && isset($para_map[$bid])) {
          $item_section[$para_map[$bid]] = $si;
        }
      }
    }
  }

  // Sort by (section delta, column delta) so section insertion for skipped
  // columns lands in document order.
  usort($items, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
  foreach ($items as [$delta, $col, $col_item, $display]) {
    $spec = $display_map[$display];
    $config = [
      'id' => 'cas_group_faces',
      'label' => 'Faces of AgSci',
      'provider' => 'osu_cas_multisite_groups',
      'label_display' => '0',
      'context_mapping' => [],
      'items' => $spec['items'],
      'terms' => $spec['terms'],
      'all_groups' => $spec['all_groups'],
      'group_override' => NULL,
    ];
    $sections = $list->getSections();
    $index = $col_item && isset($item_section[$col_item]) ? $item_section[$col_item] : NULL;
    if ($index === NULL && $col < 0) {
      // Paragraph-hosted embed. When the embed's own paragraph became a
      // section (1-column / 2-column bundles: the view sat beside the
      // paragraph's text), the listing goes into that section, after the
      // text. Only section-less bundles (viewfield, 2_column_views) need a
      // fresh section after the nearest earlier paragraph's.
      foreach ($para_structure[$nid] ?? [] as [$pdelta, $pitem]) {
        if ($pdelta === $delta && isset($item_section[$pitem])) {
          $index = $item_section[$pitem];
          break;
        }
      }
    }
    if ($index === NULL && $col < 0) {
      $after = 0;
      foreach ($para_structure[$nid] ?? [] as [$pdelta, $pitem]) {
        if ($pdelta < $delta && isset($item_section[$pitem])) {
          $after = max($after, $item_section[$pitem]);
        }
      }
      $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'faces', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
      $list->insertSection($after + 1, $section);
      $inserted_sections++;
      foreach ($item_section as $k => $si) {
        if ($si > $after) {
          $item_section[$k] = $si + 1;
        }
      }
      $index = $after + 1;
    }
    if ($index === NULL) {
      // The D7 column held only the view, so it produced no D10 section.
      // Insert one right after the nearest earlier column's section.
      $after = 0;
      foreach ($item_section as $si) {
        if ($si > $after && $si <= $delta) {
          $after = $si;
        }
      }
      // Best anchor: highest section index among earlier-delta siblings.
      $after = 0;
      foreach ($items as [$d2, $c2, $ci2, $disp2]) {
        if ($d2 < $delta && $ci2 && isset($item_section[$ci2])) {
          $after = max($after, $item_section[$ci2]);
        }
      }
      if (!$after) {
        $after = count($sections) - 1;
      }
      $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'faces', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
      $list->insertSection($after + 1, $section);
      $inserted_sections++;
      // Re-derive the item->section map: indexes after the insert shifted.
      foreach ($item_section as $k => $si) {
        if ($si > $after) {
          $item_section[$k] = $si + 1;
        }
      }
      $index = $after + 1;
    }
    $section = $list->getSections()[$index];
    $existing = $section->getComponents();
    $region = $existing ? reset($existing)->getRegion() : 'blb_region_col_1';
    $component = SectionComponent::fromArray([
      'uuid' => $uuid->generate(),
      'region' => $region,
      'configuration' => $config,
      'additional' => [],
      'weight' => max($col, 0),
    ]);
    // appendComponent() re-weights to last place; restore the column weight
    // so the listing sorts right behind its own column block (stable sort
    // keeps it after the equal-weight heading).
    $section->appendComponent($component);
    $component->setWeight(max($col, 0));
    $placed++;
  }
  $node->setNewRevision(FALSE);
  $node->setSyncing(TRUE);
  $node->save();
}
printf("Placed: %d  Sections inserted: %d  Missing nodes: %d\n", $placed, $inserted_sections, $missing_node);
