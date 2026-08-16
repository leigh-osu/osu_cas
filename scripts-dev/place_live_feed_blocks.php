<?php

/**
 * @file
 * Place osu_live_feed blocks into the layout slots D7 gave them.
 *
 * One-time repair for a database migrated before CasLayoutBase emitted
 * osu_live_feed components (rebuilds place these in-migration). Mapping from
 * the D7 audit: page nid => [D7 section delta => feed nids]. The D10 section
 * index is delta + 1 (section 0 is the Default Page Section).
 *
 * Usage: drush scr scripts-dev/place_live_feed_blocks.php
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

$placements = [
  264841 => [0 => [264836]],
  287591 => [6 => [287586]],
  286101 => [0 => [286006, 286011]],
  259331 => [6 => [260536]],
  269116 => [3 => [269631]],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$uuid = \Drupal::service('uuid');
foreach ($placements as $nid => $sections) {
  $node = $storage->load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    print "SKIP $nid: no node/layout\n";
    continue;
  }
  $list = $node->get('layout_builder__layout');
  $all = $list->getSections();
  $changed = FALSE;
  foreach ($sections as $delta => $feed_nids) {
    $index = $delta + 1;
    if (!isset($all[$index])) {
      // Pages whose D7 section held nothing but the feed block migrated with
      // no section at all -- append a fresh one-column section for the feed.
      $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'feed', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
      $list->appendSection($section);
      $all = $list->getSections();
      $index = count($all) - 1;
      print "NEW $nid section $index created\n";
      $changed = TRUE;
    }
    else {
      $section = $all[$index];
    }
    $existing = $section->getComponents();
    // Reuse the region of the section's existing content so the feed lands in
    // the same column area; blb_col_1 sections have one region anyway.
    $region = $existing ? reset($existing)->getRegion() : 'blb_region_col_1';
    $have = [];
    foreach ($existing as $c) {
      $have[$c->getPluginId()] = TRUE;
    }
    foreach ($feed_nids as $feed_nid) {
      $plugin_id = 'osu_live_feed:' . $feed_nid;
      if (isset($have[$plugin_id])) {
        print "OK $nid: $plugin_id already placed\n";
        continue;
      }
      $section->appendComponent(SectionComponent::fromArray([
        'uuid' => $uuid->generate(),
        'region' => $region,
        'configuration' => [
          'id' => $plugin_id,
          'label' => 'Live feed',
          'provider' => 'osu_live_feeds',
          'label_display' => '0',
          'context_mapping' => [],
        ],
        'additional' => [],
        'weight' => count($existing) + 1,
      ]));
      $changed = TRUE;
      print "PLACED $nid section $index: $plugin_id\n";
    }
  }
  if ($changed) {
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
  }
}
