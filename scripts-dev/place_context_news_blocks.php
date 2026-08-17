<?php

/**
 * @file
 * Place news listings and live feeds where D7 contexts placed them.
 *
 * The context module placed news_items view blocks (group news archives on
 * the per-group articles pages and unit news pages, teaser blocks on landing
 * pages) and live_feeds blocks (HAREC/MCAREC/Small Farms events, the
 * plant-breeding publications feed) into the content region by path.
 * Placements carry 'placement' => 'context'; reconcile is by that marker
 * plus the 'news (context)' section label, so reruns and the embed
 * placement scripts leave each other alone. Stale context paths whose pages
 * were deleted upstream resolve to nothing and are skipped.
 *
 * Usage: drush scr scripts-dev/place_context_news_blocks.php
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

// nid => list of cas_group_news specs.
$news = [
  16244 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  25611 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  25817 => [['display' => 'teasers', 'items' => 5, 'spotlight' => FALSE]],
  26047 => [['display' => 'teasers', 'items' => 5, 'spotlight' => FALSE]],
  26781 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  26789 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  26977 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  27356 => [['display' => 'teasers', 'items' => 3, 'spotlight' => TRUE]],
  27861 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  41366 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  46611 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  55981 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  58801 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  59111 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  79816 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  80166 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  80796 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  81226 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  95061 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  216361 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  216706 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
  272501 => [['display' => 'list', 'items' => 5, 'spotlight' => FALSE]],
];

// nid => list of feed nids (osu_live_feed:<nid> blocks).
$feeds = [
  109791 => [219536],
  109811 => [219541],
  25817 => [24743],
  239106 => [219531],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$uuid = \Drupal::service('uuid');
$placed = $missing = 0;
$nids = array_unique(array_merge(array_keys($news), array_keys($feeds)));
sort($nids);
foreach ($nids as $nid) {
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
      if (in_array($cfg['id'] ?? '', ['cas_group_news'], TRUE) && ($cfg['placement'] ?? '') === 'context') {
        $section->removeComponent($c->getUuid());
      }
      if (str_starts_with($cfg['id'] ?? '', 'osu_live_feed:') && ($cfg['placement'] ?? '') === 'context') {
        $section->removeComponent($c->getUuid());
      }
    }
  }
  for ($si = count($list->getSections()) - 1; $si >= 0; $si--) {
    $section = $list->getSections()[$si];
    if (($section->getLayoutSettings()['label'] ?? '') === 'news (context)' && !$section->getComponents()) {
      $list->removeSection($si);
    }
  }
  $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'news (context)', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
  $list->appendSection($section);
  $w = 0;
  foreach ($news[$nid] ?? [] as $spec) {
    $component = SectionComponent::fromArray([
      'uuid' => $uuid->generate(),
      'region' => 'blb_region_col_1',
      'configuration' => [
        'id' => 'cas_group_news',
        'label' => 'News listing',
        'provider' => 'osu_cas_multisite_groups',
        'label_display' => '0',
        'context_mapping' => [],
        'placement' => 'context',
        'display' => $spec['display'],
        'items' => $spec['items'],
        'spotlight' => $spec['spotlight'],
        'term' => NULL,
        'group_override' => NULL,
      ],
      'additional' => [],
      'weight' => $w,
    ]);
    $section->appendComponent($component);
    $component->setWeight($w++);
    $placed++;
  }
  foreach ($feeds[$nid] ?? [] as $feed_nid) {
    $component = SectionComponent::fromArray([
      'uuid' => $uuid->generate(),
      'region' => 'blb_region_col_1',
      'configuration' => [
        'id' => 'osu_live_feed:' . $feed_nid,
        'label' => 'Live feed',
        'provider' => 'osu_live_feeds',
        'label_display' => '0',
        'context_mapping' => [],
        'placement' => 'context',
      ],
      'additional' => [],
      'weight' => $w,
    ]);
    $section->appendComponent($component);
    $component->setWeight($w++);
    $placed++;
  }
  $node->setNewRevision(FALSE);
  $node->setSyncing(TRUE);
  $node->save();
}
printf("Placed: %d  Missing nodes: %d\n", $placed, $missing);
