<?php

/**
 * @file
 * Place cas_degree_list blocks where D7 embedded degree_fact_sheets_list.
 *
 * Each D7 embed lived in a 1-column paragraph beside its text; the
 * paragraph's migrated inline block locates the D10 section, and the card
 * grid is appended after it. Group scoping follows D7's og_group_ref
 * filters (Education 63931 for the college pages, EOU 16844).
 *
 * The degree_fact_sheet context blocks (D7 view degree_fact_sheet on every
 * fact-sheet node) are the fact sheet's own full display, which the
 * osu_cas_multisite_degrees node template already renders.
 *
 * Idempotent: cas_degree_list components carrying 'placement' => 'embed'
 * are removed before re-placing.
 *
 * Usage: drush scr scripts-dev/place_dfs_blocks.php
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

// [nid, D7 host paragraph item id, variant, group id, D7 title or NULL].
$placements = [
  [63976, 4566, 'undergrad', 63931, NULL],
  [228801, 45461, 'all', 63931, NULL],
  [63981, 45486, 'grad', 63931, NULL],
  [243591, 66476, 'undergrad_es', 63931, NULL],
  [271781, 108621, 'eou_bs', 16844, 'Bachelor of Science'],
  [271781, 108626, 'eou_minors', 16844, 'Minors'],
  [275296, 114386, 'grad', 63931, NULL],
];

$db = \Drupal::database();
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
foreach ($placements as [$nid, $item, $variant, $gid, $title]) {
  $by_node[$nid][] = [$item, $variant, $gid, $title];
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
      if (($cfg['id'] ?? '') === 'cas_degree_list' && ($cfg['placement'] ?? '') === 'embed') {
        $section->removeComponent($c->getUuid());
      }
    }
  }
  $item_section = [];
  foreach ($list->getSections() as $si => $section) {
    foreach ($section->getComponents() as $c) {
      $cfg = $c->get('configuration');
      if (str_starts_with($cfg['id'] ?? '', 'inline_block:') && !empty($cfg['block_revision_id'])) {
        $bid = $db->query('SELECT id FROM {block_content_revision} WHERE revision_id = :r', [':r' => $cfg['block_revision_id']])->fetchField();
        if ($bid && isset($para_map[$bid])) {
          $item_section[$para_map[$bid]] = $si;
        }
      }
    }
  }
  foreach ($items as [$item, $variant, $gid, $title]) {
    if (isset($item_section[$item])) {
      $section = $list->getSections()[$item_section[$item]];
    }
    else {
      // The paragraph held only the view: give it its own section at the end.
      print "NOTE $nid item $item: no migrated section, appending\n";
      $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'degrees (embed)', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
      $list->appendSection($section);
    }
    $weight = count($section->getComponents());
    $component = SectionComponent::fromArray([
      'uuid' => $uuid->generate(),
      'region' => $section->getComponents() ? reset($section->getComponents())->getRegion() : 'blb_region_col_1',
      'configuration' => [
        'id' => 'cas_degree_list',
        'label' => $title ?? 'Degree fact sheet cards',
        'provider' => 'osu_cas_multisite_degrees',
        'label_display' => $title ? 'visible' : '0',
        'context_mapping' => [],
        'placement' => 'embed',
        'variant' => $variant,
        'group_override' => $gid,
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
