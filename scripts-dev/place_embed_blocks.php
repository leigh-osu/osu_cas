<?php

/**
 * @file
 * Place the remaining D7 view embeds: fun facts, videos, feature stories,
 * people/sara_profiles listings.
 *
 * Each D7 embed is located by its host: 'fc:<id>' (adjustable-column item)
 * or 'para:<id>' (paragraph item). Their migrated D10 blocks pin the section
 * and the listing is appended after them. Hosts that produced no D10 block
 * (viewfield-only paragraphs) get a fresh section at the D7 delta position,
 * derived from the neighbouring paragraphs' sections; failing that, the end.
 *
 * Idempotent: components carrying 'placement' => 'embed' for the plugin ids
 * handled here are removed before re-placing.
 *
 * Usage: drush scr scripts-dev/place_embed_blocks.php
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

// [nid, host, D7 delta, plugin id, configuration, title|NULL].
$placements = [
  // fun facts (D7 fun_facts view).
  [235426, 'fc:26491', 7, 'cas_fun_facts', ['variant' => 'random3', 'items' => 3], NULL],
  [240061, 'fc:75791', 4, 'cas_fun_facts', ['variant' => 'illustrated', 'items' => 3], NULL],
  [254826, 'fc:75796', 6, 'cas_fun_facts', ['variant' => 'illustrated', 'items' => 3], NULL],
  [257961, 'fc:74981', 8, 'cas_fun_facts', ['variant' => 'illustrated', 'items' => 3, 'group_override' => 211281], NULL],
  [258076, 'para:86011', 0, 'cas_fun_facts', ['variant' => 'all'], NULL],
  [258991, 'para:87176', 0, 'cas_fun_facts', ['variant' => 'all'], NULL],
  // videos (D7 video_view): reels; 257961 draws from the Sulikowski group.
  [257961, 'fc:76021', 6, 'views_block:videos-reel', ['items_per_page' => 'none'], NULL],
  [254826, 'fc:76076', 7, 'views_block:videos-reel', ['items_per_page' => 'none'], NULL],
  [86026, 'para:22496', 3, 'views_block:videos-reel', ['items_per_page' => 'none'], NULL],
  [24266, 'para:74151', 1, 'views_block:videos-reel_untitled', ['items_per_page' => 'none'], NULL],
  [24294, 'para:74176', 0, 'views_block:videos-grid', ['items_per_page' => 'none'], NULL],
  // feature stories (D7 feature_story block / block_2).
  [24266, 'para:74141', 0, 'views_block:feature_stories-banner', ['items_per_page' => 'none'], NULL],
  [281051, 'para:123811', 7, 'views_block:feature_stories-banner', ['items_per_page' => 'none'], NULL],
  [241101, 'para:63006', 5, 'views_block:feature_stories-banner', ['items_per_page' => 'none'], NULL],
  [213791, 'para:25551', 6, 'views_block:feature_stories-banner', ['items_per_page' => 'none'], NULL],
  // people (D7 people block_1/2: the group's people) and sara_profiles.
  [3906, 'para:49291', 0, 'cas_group_profiles', ['display' => 'list', 'membership_types' => [], 'term' => NULL, 'grad_accept' => [], 'all_groups' => FALSE, 'exposed_filter' => 'none', 'group_override' => NULL], NULL],
  [58941, 'para:2166', 2, 'cas_group_profiles', ['display' => 'list', 'membership_types' => [], 'term' => NULL, 'grad_accept' => [], 'all_groups' => FALSE, 'exposed_filter' => 'none', 'group_override' => NULL], NULL],
  [62616, 'para:57796', 0, 'cas_group_profiles', ['display' => 'list', 'membership_types' => [466], 'term' => NULL, 'grad_accept' => [], 'all_groups' => FALSE, 'exposed_filter' => 'none', 'group_override' => NULL], NULL],
  [242866, 'para:65341', 4, 'cas_group_profiles', ['display' => 'list', 'membership_types' => [441], 'term' => NULL, 'grad_accept' => [], 'all_groups' => FALSE, 'exposed_filter' => 'none', 'group_override' => NULL], NULL],
];

// D7 paragraph structure of the host pages: nid => [delta => item id].
$para_structure = [
  235426 => [], 240061 => [], 254826 => [],
  257961 => [0 => 85861, 1 => 85866, 2 => 85871, 3 => 85991, 4 => 85856, 5 => 87191, 6 => 87276, 7 => 87196, 8 => 86001],
  258076 => [0 => 86011], 258991 => [0 => 87176],
  86026 => [0 => 16411, 1 => 16416, 2 => 22511, 3 => 22496, 4 => 22501, 5 => 22506, 6 => 16971, 7 => 16966, 8 => 16091, 9 => 16976, 10 => 16981, 11 => 16096],
  24294 => [0 => 74176], 24266 => [0 => 74141, 1 => 74151, 2 => 74156, 3 => 74171],
  86036 => [], 3906 => [0 => 49291], 58941 => [0 => 2116, 1 => 2121, 2 => 2166], 62616 => [0 => 57796],
  242866 => [0 => 65321, 1 => 65326, 2 => 65331, 3 => 65336, 4 => 65341],
  213791 => [0 => 24846, 1 => 24851, 2 => 104161, 3 => 25056, 4 => 25036, 5 => 25556, 6 => 25551],
  241101 => [0 => 62981, 1 => 62986, 2 => 62991, 3 => 62996, 4 => 63001, 5 => 63006],
  281051 => [0 => 123781, 1 => 123786, 2 => 123791, 3 => 123796, 4 => 123801, 5 => 123806, 6 => 123816, 7 => 123811],
];
$providers = ['cas_fun_facts' => 'osu_cas_multisite_groups', 'cas_group_profiles' => 'osu_cas_multisite_groups'];

$db = \Drupal::database();
$col_map = $db->query('SELECT destid1, sourceid1 FROM {migrate_map_field_collection_field_lp_adj_column__to__layout_bu} WHERE destid1 IS NOT NULL')->fetchAllKeyed();
$para_map = [];
foreach ($db->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND TABLE_NAME LIKE 'migrate\_map\_paragraph\_%'")->fetchCol() as $table) {
  foreach ($db->query("SELECT destid1, sourceid1 FROM {" . $table . "} WHERE destid1 IS NOT NULL") as $row) {
    $para_map[$row->destid1] = (int) $row->sourceid1;
  }
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$uuid = \Drupal::service('uuid');
$block_manager = \Drupal::service('plugin.manager.block');
$handled_ids = ['cas_fun_facts', 'cas_group_profiles', 'views_block:videos-reel', 'views_block:videos-reel_untitled', 'views_block:videos-grid', 'views_block:feature_stories-banner'];

$by_node = [];
foreach ($placements as [$nid, $host, $delta, $plugin, $config, $title]) {
  $by_node[$nid][] = [$host, $delta, $plugin, $config, $title];
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
      if (in_array($cfg['id'] ?? '', $handled_ids, TRUE) && ($cfg['placement'] ?? '') === 'embed') {
        $section->removeComponent($c->getUuid());
      }
    }
  }
  for ($si = count($list->getSections()) - 1; $si >= 0; $si--) {
    $section = $list->getSections()[$si];
    if (($section->getLayoutSettings()['label'] ?? '') === 'embed' && !$section->getComponents()) {
      $list->removeSection($si);
    }
  }
  // Locate sections by D7 source item.
  $locate = function () use ($list, $db, $col_map, $para_map) {
    $found = [];
    foreach ($list->getSections() as $si => $section) {
      foreach ($section->getComponents() as $c) {
        $cfg = $c->get('configuration');
        if (str_starts_with($cfg['id'] ?? '', 'inline_block:') && !empty($cfg['block_revision_id'])) {
          $bid = $db->query('SELECT id FROM {block_content_revision} WHERE revision_id = :r', [':r' => $cfg['block_revision_id']])->fetchField();
          if ($bid && isset($col_map[$bid])) {
            $found['fc:' . $col_map[$bid]] = $si;
          }
          if ($bid && isset($para_map[$bid])) {
            $found['para:' . $para_map[$bid]] = $si;
          }
        }
      }
    }
    return $found;
  };
  usort($items, fn($a, $b) => $a[1] <=> $b[1]);
  foreach ($items as [$host, $delta, $plugin, $config, $title]) {
    if (!$block_manager->hasDefinition($plugin)) {
      print "MISS $nid: no block plugin $plugin\n";
      $missed++;
      continue;
    }
    $found = $locate();
    $index = $found[$host] ?? NULL;
    if ($index === NULL) {
      // A viewfield-only paragraph: new section after the nearest earlier
      // paragraph's section (or at the very end).
      $after = -1;
      $before = NULL;
      foreach ($para_structure[$nid] ?? [] as $pdelta => $pitem) {
        if ($pdelta < $delta && isset($found['para:' . $pitem])) {
          $after = max($after, $found['para:' . $pitem]);
        }
        if ($pdelta > $delta && isset($found['para:' . $pitem])) {
          $before = $before === NULL ? $found['para:' . $pitem] : min($before, $found['para:' . $pitem]);
        }
      }
      $insert_at = $after >= 0 ? $after + 1 : ($before !== NULL ? $before : count($list->getSections()));
      $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'embed', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
      $list->insertSection($insert_at, $section);
      print "NOTE $nid $host: no migrated section, new section at $insert_at\n";
    }
    else {
      $section = $list->getSections()[$index];
    }
    $weight = count($section->getComponents());
    $configuration = [
      'id' => $plugin,
      'label' => $title ?? '',
      'provider' => $providers[$plugin] ?? 'views',
      'label_display' => $title ? 'visible' : '0',
      'context_mapping' => [],
      'placement' => 'embed',
    ] + $config;
    if (str_starts_with($plugin, 'views_block:')) {
      $configuration['views_label'] = $title ?? '';
    }
    $component = SectionComponent::fromArray([
      'uuid' => $uuid->generate(),
      'region' => $section->getComponents() ? reset($section->getComponents())->getRegion() : 'blb_region_col_1',
      'configuration' => $configuration,
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
