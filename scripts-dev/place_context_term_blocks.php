<?php

/**
 * @file
 * Place cas_content_by_term blocks where D7 contexts placed the
 * articles_by_subject / articles_coarec topic listings.
 *
 * Also carries the Malheur Experiment Station crop/topic publication lists
 * (malhuer_publications, 31 contexts) as titles-only listings; the stray
 * exposed "Has taxonomy term" autocomplete on two of those displays is
 * treated as the fixed term it defaulted to.
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
  79961 => [['title' => 'Publications', 'exposed' => 'list_nursery', 'bundles' => ['story', 'page'], 'all' => [2616]]],
  63566 => [['title' => 'Publications', 'exposed' => 'list_nursery', 'bundles' => ['story', 'page'], 'all' => [2616]]],
  79936 => [['title' => 'Publications', 'exposed' => 'archive_nursery', 'bundles' => ['story'], 'any' => [2591, 2596, 2601, 2606, 2611, 2616, 2621, 2626]]],
  // Spotted Wing Drosophila.
  80111 => [['title' => 'Resources', 'exposed' => 'list_swd', 'all' => [2866]]],
  80101 => [['title' => 'Resources', 'exposed' => 'list_swd', 'all' => [2861]]],
  80106 => [['title' => 'Resources', 'exposed' => 'list_swd', 'all' => [2871]]],
  // Beaver Turf.
  77606 => [['title' => 'Publications', 'exposed' => 'list_turf', 'bundles' => ['story', 'page'], 'all' => [2996]]],
  77596 => [['title' => 'Publications', 'exposed' => 'list_turf', 'bundles' => ['story', 'page'], 'all' => [3001]]],
  77601 => [['title' => 'Publications', 'exposed' => 'list_turf', 'bundles' => ['story', 'page'], 'all' => [3006]]],
  // Oregon Vegetables.
  80271 => [['title' => 'Videos', 'exposed' => 'archive_veg_video', 'bundles' => ['video'], 'any' => [3211, 3216, 3221, 3226]]],
  83621 => [['title' => 'Videos', 'bundles' => ['video'], 'all' => [3211]]],
  77586 => [['title' => 'Publications', 'exposed' => 'list_veg', 'bundles' => ['story'], 'any' => [3211, 3216, 3221, 3226]]],
  83116 => [['title' => 'Videos', 'bundles' => ['video'], 'all' => [3221]]],
  83656 => [['title' => 'Videos', 'bundles' => ['video'], 'all' => [3226]]],
  // COAREC annual reports (year AND publication-type 5891) and topics.
  166661 => [['bundles' => ['story'], 'all' => [5966, 5891]]],
  166656 => [['bundles' => ['story'], 'all' => [5971, 5891]]],
  166616 => [['bundles' => ['story'], 'all' => [5976, 5891]]],
  166651 => [['bundles' => ['story'], 'all' => [5981, 5891]]],
  166646 => [['bundles' => ['story'], 'all' => [5986, 5891]]],
  166641 => [['bundles' => ['story'], 'all' => [5991, 5891]]],
  166636 => [['bundles' => ['story'], 'all' => [5996, 5891]]],
  118361 => [['title' => 'Disease Control in Carrot Seeds', 'bundles' => ['story'], 'all' => [5891, 12511], 'any' => [12141, 14796]]],
  // COAREC article archive (articles_coarec block_5: exposed year/author/topic).
  118256 => [['title' => 'Annual Reports', 'exposed' => 'archive_coarec', 'bundles' => ['story']]],
  // Malheur Experiment Station crop/topic publication lists (D7
  // malhuer_publications block_N per context): titles only, stories tagged
  // with the crop / Malheur-topic / publication-type term.
  45271 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6061]]], // Alfalfa
  45276 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6101]]], // Onion
  45281 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6106]]], // Potatoes
  45286 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6146]]], // Sugar Beets
  45291 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6081]]], // Corn
  45296 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6171]]], // Wheat
  45301 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6091]]], // Mint
  45306 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6086]]], // Dry Beans
  45311 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6156]]], // Teff
  45316 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6116]]], // Poplar Trees
  45321 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6096]]], // Wildflowers
  45326 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6131]]], // Soybeans
  45331 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6111]]], // Pumpkin
  45336 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6151]]], // Sweet Potatoes
  45341 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6066]]], // Asparagus
  45346 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6076]]], // Camelina
  45391 => [['style' => 'titles', 'bundles' => ['story'], 'all' => [5771], 'title' => 'Cooperative Extension Brochures']], // Extension Brochures
  112771 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6121]]], // Quinoa
  112776 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6126]]], // Rangeland
  112786 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6141]]], // Stevia
  112811 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6136]]], // Squash and Gourds
  112816 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6161]]], // Tomato
  112821 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6166]]], // Veratrum
  112826 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [5601]]], // Irrigation and Water Management
  112831 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [5631]], ['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [5831]]], // Weed Control / Pest Control
  112841 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6176]]], // Yellow Nutsedge
  112846 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6181]]], // Yew Trees
  112896 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [5606]]], // Weather
  115931 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [6071]]], // California Yerba Santa
  249791 => [['style' => 'titles', 'heading' => TRUE, 'title' => 'Malheur Publications', 'bundles' => ['story'], 'all' => [20361]]], // Refereed Journals
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
        'label' => $spec['title'] ?? 'Topic listing',
        'provider' => 'osu_cas_multisite_groups',
        'label_display' => isset($spec['title']) ? 'visible' : '0',
        'context_mapping' => [],
        'placement' => 'context',
        'items' => 0,
        'style' => $spec['style'] ?? 'full',
        'term_heading' => !empty($spec['heading']),
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
