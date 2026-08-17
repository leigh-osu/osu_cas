<?php

/**
 * @file
 * Place plain Views blocks where D7 contexts placed group-scoped listings.
 *
 * For views whose only context is "the current group" (D7 og_context), the
 * D10 view's gid contextual filter defaults to cas_current_group, so a
 * views_block component with no settings is enough: projects, image
 * galleries, art galleries, video reels, plant varieties, weeds, courses,
 * topic-tagged article lists. One appended section per page (label 'views
 * (context)'), blocks in D7 context weight order.
 *
 * Idempotent: components carrying 'placement' => 'context' whose plugin
 * id starts with views_block: are removed before re-placing.
 *
 * Usage: drush scr scripts-dev/place_context_views_blocks.php
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

// nid => list of ['view-display', title|NULL].
$placements = [
  // projects (D7 projects view).
  257141 => [['projects-active', 'Active Projects']],
  256861 => [['projects-active', 'Active Projects']],
  257241 => [['projects-completed', 'Completed Projects']],
  256866 => [['projects-completed', 'Completed Projects']],
  79786 => [['projects-completed', 'Completed Projects']],
  80306 => [['projects-organic', 'Projects']],
  // publications (D7 biblio2): group libraries, recent five, searchable.
  25817 => [['publications_by_group-list', 'Publications']],
  409 => [['publications_by_group-list', 'Publications']],
  24733 => [['publications_by_group-list', 'Publications']],
  67446 => [['publications_by_group-list', 'Publications']],
  84566 => [['publications_by_group-list', 'Publications']],
  89136 => [['publications_by_group-list', 'Publications']],
  95406 => [['publications_by_group-list', 'Publications']],
  24279 => [['publications_by_group-recent', 'Recent Publications']],
  103736 => [['publications_by_group-search', 'Publications']],
  118256 => [['publications_by_group-search', 'Publications']],
  99021 => [['publications_by_group-search', 'Publications']],
  // image galleries (D7 image_gallery block_4): the group's album images.
  80056 => [['videos-reel', NULL], ['image_galleries-group_images', NULL]],
  86251 => [['image_galleries-group_images', NULL]],
  16044 => [['image_galleries-group_images', NULL]],
  16046 => [['image_galleries-group_images', NULL]],
  // art about agriculture: artists represented.
  53906 => [['art_artists-artists', NULL]],
  // hop cultivars, weeds, courses.
  56814 => [['plant_varieties-cards', NULL]],
  63571 => [['weeds-table', 'Weed ID']],
  40726 => [['courses-table', 'Courses']],
  43626 => [['courses-table', 'Courses']],
  // videos (D7 video_view): reels and the video grid.
  216706 => [['videos-reel', NULL]],
  80176 => [['videos-grid', NULL]],
  33041 => [['videos-reel', NULL]],
  96851 => [['videos-reel', NULL]],
  214676 => [['videos-reel', NULL]],
  285646 => [['videos-reel', NULL]],
  // feature stories (D7 feature_story block on the bioenergy pages, feature pages list for IYPH).
  26047 => [['feature_stories-banner', NULL]],
  26240 => [['feature_stories-banner', NULL]],
  217366 => [['feature_stories-pages', NULL]],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$uuid = \Drupal::service('uuid');
$block_manager = \Drupal::service('plugin.manager.block');
$placed = $missing = 0;
foreach ($placements as $nid => $items) {
  $node = $storage->load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    print "SKIP $nid: no node/layout\n";
    $missing += count($items);
    continue;
  }
  $list = $node->get('layout_builder__layout');
  foreach ($list->getSections() as $section) {
    foreach ($section->getComponents() as $c) {
      $cfg = $c->get('configuration');
      if (str_starts_with($cfg['id'] ?? '', 'views_block:') && ($cfg['placement'] ?? '') === 'context') {
        $section->removeComponent($c->getUuid());
      }
    }
  }
  for ($si = count($list->getSections()) - 1; $si >= 0; $si--) {
    $section = $list->getSections()[$si];
    if (($section->getLayoutSettings()['label'] ?? '') === 'views (context)' && !$section->getComponents()) {
      $list->removeSection($si);
    }
  }
  $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'views (context)', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
  $list->appendSection($section);
  foreach ($items as $i => [$view_display, $title]) {
    $plugin_id = 'views_block:' . $view_display;
    if (!$block_manager->hasDefinition($plugin_id)) {
      print "MISS $nid: no block plugin $plugin_id\n";
      $missing++;
      continue;
    }
    $component = SectionComponent::fromArray([
      'uuid' => $uuid->generate(),
      'region' => 'blb_region_col_1',
      'configuration' => [
        'id' => $plugin_id,
        'label' => $title ?? '',
        'provider' => 'views',
        'label_display' => $title ? 'visible' : '0',
        'views_label' => $title ?? '',
        'items_per_page' => 'none',
        'context_mapping' => [],
        'placement' => 'context',
      ],
      'additional' => [],
      'weight' => $i,
    ]);
    $section->appendComponent($component);
    $component->setWeight($i);
    $placed++;
  }
  $node->setNewRevision(FALSE);
  $node->setSyncing(TRUE);
  $node->save();
}
printf("Placed: %d  Missing: %d\n", $placed, $missing);
