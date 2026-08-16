<?php

/**
 * @file
 * Place cas_group_news blocks where D7 embedded the news_items views.
 *
 * The D10 fold of news_items / news_items_2019 / news_items_larch. Each D7
 * embed becomes a "Group news listing" block: teasers or the paged archive,
 * item count, spotlight-only, and a term limit for the CAS-section/tag
 * scoped displays. The group is not configured -- the block's view argument
 * defaults to the page's own group. Section targeting is by provenance via
 * the field_lp_adj_column migrate map, exactly as
 * place_profiles_group_membership.php does; existing cas_group_news
 * components are cleared per node first, so reruns fully reconcile.
 * Generated from the D7 view exports + embed audit.
 *
 * Usage: drush scr scripts-dev/place_news_blocks.php
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

$display_map = [
  'news_items_2019|block_1' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|block_10' => ['style' => 'teasers', 'items' => 1, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|block_11' => ['style' => 'teasers', 'items' => 3, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|block_12' => ['style' => 'teasers', 'items' => 1, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|block_13' => ['style' => 'teasers', 'items' => 4, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|block_14' => ['style' => 'list', 'items' => 100, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|block_15' => ['style' => 'teasers', 'items' => 3, 'spotlight' => FALSE, 'term' => 19656],
  'news_items_2019|block_16' => ['style' => 'list', 'items' => 100, 'spotlight' => FALSE, 'term' => 19656],
  'news_items_2019|block_17' => ['style' => 'teasers', 'items' => 4, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|block_18' => ['style' => 'list', 'items' => 100, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|block_2' => ['style' => 'list', 'items' => 100, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|block_3' => ['style' => 'teasers', 'items' => 5, 'spotlight' => TRUE, 'term' => NULL],
  'news_items_2019|block_4' => ['style' => 'teasers', 'items' => 5, 'spotlight' => TRUE, 'term' => NULL],
  'news_items_2019|block_5' => ['style' => 'teasers', 'items' => 1, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|block_6' => ['style' => 'teasers', 'items' => 3, 'spotlight' => TRUE, 'term' => NULL],
  'news_items_2019|block_7' => ['style' => 'teasers', 'items' => 3, 'spotlight' => TRUE, 'term' => NULL],
  'news_items_2019|block_8' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|block_9' => ['style' => 'list', 'items' => 100, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_2019|default' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|attachment_1' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_1' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_10' => ['style' => 'teasers', 'items' => 1, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_11' => ['style' => 'teasers', 'items' => 3, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_12' => ['style' => 'teasers', 'items' => 100, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_13' => ['style' => 'teasers', 'items' => 1, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_14' => ['style' => 'teasers', 'items' => 3, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_15' => ['style' => 'teasers', 'items' => 3, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_16' => ['style' => 'teasers', 'items' => 3, 'spotlight' => TRUE, 'term' => 10],
  'news_items_larch|block_17' => ['style' => 'teasers', 'items' => 25, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_18' => ['style' => 'list', 'items' => 100, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_19' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_2' => ['style' => 'list', 'items' => 100, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_20' => ['style' => 'teasers', 'items' => 3, 'spotlight' => FALSE, 'term' => 20371],
  'news_items_larch|block_21' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => 20371],
  'news_items_larch|block_22' => ['style' => 'teasers', 'items' => 3, 'spotlight' => FALSE, 'term' => 9],
  'news_items_larch|block_23' => ['style' => 'teasers', 'items' => 3, 'spotlight' => FALSE, 'term' => 9],
  'news_items_larch|block_24' => ['style' => 'teasers', 'items' => 10, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_25' => ['style' => 'teasers', 'items' => 0, 'spotlight' => FALSE, 'term' => 9],
  'news_items_larch|block_3' => ['style' => 'teasers', 'items' => 5, 'spotlight' => TRUE, 'term' => NULL],
  'news_items_larch|block_4' => ['style' => 'teasers', 'items' => 5, 'spotlight' => TRUE, 'term' => NULL],
  'news_items_larch|block_5' => ['style' => 'teasers', 'items' => 1, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_6' => ['style' => 'teasers', 'items' => 3, 'spotlight' => TRUE, 'term' => NULL],
  'news_items_larch|block_7' => ['style' => 'teasers', 'items' => 3, 'spotlight' => TRUE, 'term' => NULL],
  'news_items_larch|block_8' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|block_9' => ['style' => 'list', 'items' => 100, 'spotlight' => FALSE, 'term' => NULL],
  'news_items_larch|default' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items|block' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items|block_1' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items|block_10' => ['style' => 'teasers', 'items' => 3, 'spotlight' => FALSE, 'term' => NULL],
  'news_items|block_11' => ['style' => 'list', 'items' => 99, 'spotlight' => FALSE, 'term' => NULL],
  'news_items|block_12' => ['style' => 'teasers', 'items' => 5, 'spotlight' => TRUE, 'term' => NULL],
  'news_items|block_2' => ['style' => 'list', 'items' => 100, 'spotlight' => FALSE, 'term' => NULL],
  'news_items|block_3' => ['style' => 'teasers', 'items' => 5, 'spotlight' => TRUE, 'term' => NULL],
  'news_items|block_4' => ['style' => 'teasers', 'items' => 5, 'spotlight' => TRUE, 'term' => NULL],
  'news_items|block_5' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items|block_6' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items|block_7' => ['style' => 'teasers', 'items' => 3, 'spotlight' => FALSE, 'term' => NULL],
  'news_items|block_8' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
  'news_items|block_9' => ['style' => 'list', 'items' => 100, 'spotlight' => FALSE, 'term' => NULL],
  'news_items|default' => ['style' => 'teasers', 'items' => 5, 'spotlight' => FALSE, 'term' => NULL],
];

// [node nid, D7 section delta, D7 column delta (-1 = non-column embed),
//  D7 column item id (0 = n/a), D7 view|display].
$placements = [
  [215, 10, -1, 0, 'news_items|block_1'],
  [16132, 0, -1, 0, 'news_items|block_10'],
  [16845, 6, -1, 0, 'news_items_2019|block_3'],
  [24266, 3, -1, 0, 'news_items|block_1'],
  [26240, 1, 0, 55351, 'news_items_larch|block_1'],
  [46176, 3, -1, 0, 'news_items|block_7'],
  [67726, 2, 0, 55226, 'news_items_larch|block_1'],
  [87331, 13, -1, 0, 'news_items|block_1'],
  [99391, 1, 0, 55521, 'news_items_larch|block_2'],
  [109801, 0, -1, 0, 'news_items_2019|block_1'],
  [109811, 4, -1, 0, 'news_items_2019|block_5'],
  [109811, 4, -1, 0, 'news_items_2019|block_6'],
  [111691, 0, 0, 117961, 'news_items_larch|block_2'],
  [113066, 11, -1, 0, 'news_items_2019|block_1'],
  [167221, 5, -1, 0, 'news_items|block_1'],
  [213586, 2, -1, 0, 'news_items_2019|block_1'],
  [230226, 0, -1, 0, 'news_items_larch|block_12'],
  [230417, 2, 1, 17947, 'news_items_2019|block_5'],
  [230417, 2, 2, 17952, 'news_items_2019|block_6'],
  [230866, 7, 0, 18776, 'news_items_2019|block_5'],
  [230866, 7, 1, 27376, 'news_items_2019|block_6'],
  [230941, 3, 1, 27396, 'news_items_larch|block_16'],
  [231351, 4, 0, 21131, 'news_items_2019|block_5'],
  [231351, 4, 1, 21136, 'news_items_2019|block_6'],
  [232121, 5, 0, 21091, 'news_items_2019|block_5'],
  [232121, 5, 1, 21096, 'news_items_2019|block_6'],
  [234536, 3, 0, 25321, 'news_items_larch|block_6'],
  [235426, 7, 0, 26486, 'news_items_larch|block_3'],
  [236186, 1, 0, 27216, 'news_items_2019|block_9'],
  [238231, 6, 2, 31156, 'news_items_2019|block_6'],
  [238286, 0, 0, 31166, 'news_items_larch|block_2'],
  [239581, 10, 0, 33991, 'news_items_2019|block_6'],
  [240061, 4, 0, 55496, 'news_items_larch|block_3'],
  [241021, 0, 0, 37071, 'news_items_2019|block_2'],
  [241511, 5, 1, 38276, 'news_items_2019|block_5'],
  [241511, 5, 2, 38281, 'news_items_2019|block_6'],
  [241596, 6, 0, 38571, 'news_items_larch|block_14'],
  [241596, 6, 1, 38556, 'news_items_larch|block_15'],
  [242351, 1, 1, 40241, 'news_items_2019|block_6'],
  [242761, 0, -1, 0, 'news_items_larch|block_2'],
  [242866, 4, -1, 0, 'news_items|block_1'],
  [243131, 7, 1, 41396, 'news_items_2019|block_17'],
  [243376, 0, 0, 42251, 'news_items_larch|block_17'],
  [243561, 1, 0, 42696, 'news_items_2019|block_1'],
  [243621, 2, 0, 42911, 'news_items_2019|block_1'],
  [243961, 3, 0, 43546, 'news_items_2019|block_11'],
  [243971, 5, 0, 43646, 'news_items_2019|block_1'],
  [243986, 4, 0, 43721, 'news_items_larch|block_7'],
  [244001, 3, 0, 43821, 'news_items_2019|block_1'],
  [244221, 0, 0, 44341, 'news_items_larch|block_2'],
  [245126, 6, 0, 45241, 'news_items_larch|block_7'],
  [245181, 8, 1, 45376, 'news_items_larch|block_1'],
  [245356, 13, 1, 45981, 'news_items_larch|block_6'],
  [246456, 6, 1, 46236, 'news_items_larch|block_6'],
  [246476, 9, 0, 46361, 'news_items_larch|block_19'],
  [246556, 4, 0, 46556, 'news_items_larch|block_19'],
  [246791, 3, 0, 47141, 'news_items_larch|block_19'],
  [246806, 10, 0, 47306, 'news_items_larch|block_19'],
  [246926, 6, 0, 55206, 'news_items_larch|block_1'],
  [246996, 0, 0, 47606, 'news_items_2019|block_18'],
  [247147, 3, 0, 47862, 'news_items_larch|block_1'],
  [247446, 6, 1, 55501, 'news_items_larch|block_6'],
  [247511, 2, 0, 48756, 'news_items_larch|block_19'],
  [247551, 11, 1, 48976, 'news_items_larch|block_19'],
  [247691, 2, 2, 54641, 'news_items_larch|block_6'],
  [249786, 3, 1, 53821, 'news_items_larch|block_20'],
  [250356, 3, 0, 54996, 'news_items_larch|block_19'],
  [250461, 0, 0, 55101, 'news_items_larch|block_21'],
  [253676, 7, 0, 62166, 'news_items_larch|block_19'],
  [254826, 6, 0, 67036, 'news_items_larch|block_6'],
  [255621, 0, 0, 67066, 'news_items_larch|block_2'],
  [256486, 6, 0, 76706, 'news_items_larch|attachment_1'],
  [256686, 4, 0, 73551, 'news_items_larch|block_6'],
  [256751, 0, 0, 73556, 'news_items_larch|block_2'],
  [257816, 0, 0, 74861, 'news_items_larch|block_2'],
  [258041, 0, 0, 74956, 'news_items_larch|block_2'],
  [258616, 8, 0, 75716, 'news_items_larch|block_3'],
  [261626, 5, 0, 82511, 'news_items_larch|block_19'],
  [262291, 0, 0, 83971, 'news_items_larch|block_25'],
  [263276, 0, 0, 86801, 'news_items_2019|block_2'],
  [263371, 8, 0, 86996, 'news_items_larch|block_19'],
  [264696, 0, -1, 0, 'news_items_larch|block_2'],
  [265681, 2, -1, 0, 'news_items|block_12'],
  [265721, 0, 0, 90526, 'news_items_2019|block_2'],
  [268321, 6, 0, 96121, 'news_items_larch|block_6'],
  [278336, 8, 0, 116826, 'news_items_larch|block_22'],
  [281481, 0, 0, 123751, 'news_items_larch|block_2'],
  [282646, 0, 0, 125841, 'news_items_larch|block_2'],
  [283166, 11, -1, 0, 'news_items|block_1'],
  [284306, 5, 2, 130211, 'news_items_2019|block_15'],
  [284891, 4, 0, 132016, 'news_items_2019|block_16'],
  [286486, 6, 0, 134611, 'news_items_larch|block_6'],
  [286501, 0, 0, 134556, 'news_items_larch|block_2'],
  [287516, 0, 0, 137186, 'news_items_larch|block_2'],
  [288031, 5, 1, 138536, 'news_items_2019|block_6'],
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
      if (($c->get('configuration')['id'] ?? '') === 'cas_group_news') {
        $section->removeComponent($c->getUuid());
      }
    }
  }
  for ($si = count($list->getSections()) - 1; $si >= 0; $si--) {
    $section = $list->getSections()[$si];
    if (($section->getLayoutSettings()['label'] ?? '') === 'news' && !$section->getComponents()) {
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
      'id' => 'cas_group_news',
      'label' => 'News listing',
      'provider' => 'osu_cas_multisite_groups',
      'label_display' => '0',
      'context_mapping' => [],
      'display' => $spec['style'],
      'items' => $spec['items'],
      'spotlight' => $spec['spotlight'],
      'term' => $spec['term'],
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
      $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'news', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
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
      $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'news', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
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
