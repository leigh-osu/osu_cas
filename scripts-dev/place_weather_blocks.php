<?php

/**
 * @file
 * Place cas_weather blocks where D7 embedded the weather views.
 *
 * D7 embedded daily_weather_data displays through viewfields in
 * adjustable-column paragraphs on two pages: the Malheur Weather Station
 * home (current-month daily table under its "MES Daily Weather Report"
 * heading) and the Hyslop Growing Degree Days page (the current-GDD
 * headline and this year's GDD table under its year heading). Each
 * column item's migrated D10 block locates the section; the weather block
 * is appended there. The three context-placed weather blocks on the Malheur
 * home (current_weather_table) rendered empty on D7 and are not recreated.
 *
 * Idempotent: cas_weather components carrying 'placement' => 'embed' are
 * removed before re-placing.
 *
 * Usage: drush scr scripts-dev/place_weather_blocks.php
 */

use Drupal\layout_builder\SectionComponent;

// [nid, D7 column item id, station, table, period].
$placements = [
  [239326, 33161, 'malheur', 'daily_month', 'current'],
  [258371, 87906, 'hyslop', 'gdd_current', 'current'],
  [258371, 87076, 'hyslop', 'gdd_year', '2026'],
  // Items 87761 and 75236..75251 (2020-2023 tables) exist only in older
  // paragraph revisions of 258371; live D7 renders one headline and the
  // current year's table.
];

$db = \Drupal::database();
$col_map = $db->query('SELECT destid1, sourceid1 FROM {migrate_map_field_collection_field_lp_adj_column__to__layout_bu} WHERE destid1 IS NOT NULL')->fetchAllKeyed();
$storage = \Drupal::entityTypeManager()->getStorage('node');
$uuid = \Drupal::service('uuid');

$by_node = [];
foreach ($placements as [$nid, $item, $station, $table, $period]) {
  $by_node[$nid][] = [$item, $station, $table, $period];
}

$placed = $missed = 0;
foreach ($by_node as $nid => $items) {
  $node = $storage->load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    print "SKIP $nid: no node/layout\n";
    $missed += count($items);
    continue;
  }
  $list = $node->get('layout_builder__layout');
  foreach ($list->getSections() as $section) {
    foreach ($section->getComponents() as $c) {
      $cfg = $c->get('configuration');
      if (($cfg['id'] ?? '') === 'cas_weather' && ($cfg['placement'] ?? '') === 'embed') {
        $section->removeComponent($c->getUuid());
      }
    }
  }
  // Column item id -> section index via the migrated inline blocks.
  $item_section = [];
  foreach ($list->getSections() as $si => $section) {
    foreach ($section->getComponents() as $c) {
      $cfg = $c->get('configuration');
      if (str_starts_with($cfg['id'] ?? '', 'inline_block:') && !empty($cfg['block_revision_id'])) {
        $bid = $db->query('SELECT id FROM {block_content_revision} WHERE revision_id = :r', [':r' => $cfg['block_revision_id']])->fetchField();
        if ($bid && isset($col_map[$bid])) {
          $item_section[(int) $col_map[$bid]] = $si;
        }
      }
    }
  }
  foreach ($items as [$item, $station, $table, $period]) {
    if (!isset($item_section[$item])) {
      print "MISS $nid item $item: no section\n";
      $missed++;
      continue;
    }
    $section = $list->getSections()[$item_section[$item]];
    $weight = count($section->getComponents());
    $component = SectionComponent::fromArray([
      'uuid' => $uuid->generate(),
      'region' => $section->getComponents() ? reset($section->getComponents())->getRegion() : 'blb_region_col_1',
      'configuration' => [
        'id' => 'cas_weather',
        'label' => 'Weather station table',
        'provider' => 'osu_cas_weather',
        'label_display' => '0',
        'context_mapping' => [],
        'placement' => 'embed',
        'station' => $station,
        'table' => $table,
        'period' => $period,
      ],
      'additional' => [],
      'weight' => $weight,
    ]);
    $section->appendComponent($component);
    $component->setWeight($weight);
    $placed++;
  }
  $node->setNewRevision(FALSE);
  $node->setSyncing(TRUE);
  $node->save();
}
printf("Placed: %d  Missed: %d\n", $placed, $missed);
