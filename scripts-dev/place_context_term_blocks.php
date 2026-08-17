<?php

/**
 * @file
 * Place cas_content_by_term blocks where D7 contexts placed the
 * articles_by_subject / articles_coarec topic listings.
 *
 * Approximations, deliberate: broad "is tagged for this site" guard lists
 * are dropped where a selective term set exists (SWD/turf/NGC primaries);
 * the OV all-content lists use the four OV section tags as an any-of set;
 * NGC publications uses its two pub-tag groups. Placements carry
 * 'placement' => 'context' in a 'topics (context)' section; reconcile is by
 * that pair, so reruns are clean.
 *
 * Usage: drush scr scripts-dev/place_context_term_blocks.php
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

// nid => list of cas_content_by_term settings.
$placements = [
  // Nursery, Greenhouse & Christmas Trees.
  79961 => [['exposed' => 'list_nursery', 'bundles' => ['story', 'page'], 'all' => [2616]]],
  63566 => [['exposed' => 'list_nursery', 'bundles' => ['story', 'page'], 'all' => [2616]]],
  79936 => [['exposed' => 'archive_nursery', 'bundles' => ['story'], 'any' => [2591, 2596, 2601, 2606, 2611, 2616, 2621, 2626]]],
  // Spotted Wing Drosophila.
  80111 => [['exposed' => 'list_swd', 'all' => [2866]]],
  80101 => [['exposed' => 'list_swd', 'all' => [2861]]],
  80106 => [['exposed' => 'list_swd', 'all' => [2871]]],
  // Beaver Turf.
  77606 => [['exposed' => 'list_turf', 'bundles' => ['story', 'page'], 'all' => [2996]]],
  77596 => [['exposed' => 'list_turf', 'bundles' => ['story', 'page'], 'all' => [3001]]],
  77601 => [['exposed' => 'list_turf', 'bundles' => ['story', 'page'], 'all' => [3006]]],
  // Oregon Vegetables.
  80271 => [['exposed' => 'archive_veg_video', 'bundles' => ['video'], 'any' => [3211, 3216, 3221, 3226]]],
  83621 => [['bundles' => ['video'], 'all' => [3211]]],
  77586 => [['exposed' => 'list_veg', 'bundles' => ['story'], 'any' => [3211, 3216, 3221, 3226]]],
  83116 => [['bundles' => ['video'], 'all' => [3221]]],
  83656 => [['bundles' => ['video'], 'all' => [3226]]],
  // COAREC annual reports (year AND publication-type 5891) and topics.
  166661 => [['bundles' => ['story'], 'all' => [5966, 5891]]],
  166656 => [['bundles' => ['story'], 'all' => [5971, 5891]]],
  166616 => [['bundles' => ['story'], 'all' => [5976, 5891]]],
  166651 => [['bundles' => ['story'], 'all' => [5981, 5891]]],
  166646 => [['bundles' => ['story'], 'all' => [5986, 5891]]],
  166641 => [['bundles' => ['story'], 'all' => [5991, 5891]]],
  166636 => [['bundles' => ['story'], 'all' => [5996, 5891]]],
  118361 => [['bundles' => ['story'], 'all' => [5891, 12511], 'any' => [12141, 14796]]],
  // COAREC article archive (articles_coarec block_5: exposed year/author/topic).
  118256 => [['exposed' => 'archive_coarec', 'bundles' => ['story']]],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$uuid = \Drupal::service('uuid');
$placed = $missing = 0;
foreach ($placements as $nid => $items) {
  $node = $storage->load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    print "SKIP $nid: no node/layout\n";
    $missing++;
    continue;
  }
  $list = $node->get('layout_builder__layout');
  foreach ($list->getSections() as $section) {
    foreach ($section->getComponents() as $c) {
      $cfg = $c->get('configuration');
      if (($cfg['id'] ?? '') === 'cas_content_by_term' && ($cfg['placement'] ?? '') === 'context') {
        $section->removeComponent($c->getUuid());
      }
    }
  }
  for ($si = count($list->getSections()) - 1; $si >= 0; $si--) {
    $section = $list->getSections()[$si];
    if (($section->getLayoutSettings()['label'] ?? '') === 'topics (context)' && !$section->getComponents()) {
      $list->removeSection($si);
    }
  }
  $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'topics (context)', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
  $list->appendSection($section);
  foreach ($items as $i => $spec) {
    $component = SectionComponent::fromArray([
      'uuid' => $uuid->generate(),
      'region' => 'blb_region_col_1',
      'configuration' => [
        'id' => 'cas_content_by_term',
        'label' => 'Topic listing',
        'provider' => 'osu_cas_multisite_groups',
        'label_display' => '0',
        'context_mapping' => [],
        'placement' => 'context',
        'items' => 0,
        'bundles' => $spec['bundles'] ?? [],
        'terms_all' => $spec['all'] ?? [],
        'terms_any' => $spec['any'] ?? [],
        'exposed_filter' => $spec['exposed'] ?? 'none',
        'all_groups' => FALSE,
        'group_override' => NULL,
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
printf("Placed: %d  Missing nodes: %d\n", $placed, $missing);
