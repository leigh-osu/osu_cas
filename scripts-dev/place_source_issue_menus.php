<?php

/**
 * @file
 * Put each The Source issue's menu into its pages as a horizontal menu bar.
 *
 * D7's the_source_* contexts placed the issue menu (menu-fall-2023, ...) in
 * the sidebar of every page whose alias sits under the issue's path
 * (thesource/fall-2023*). Here the menu becomes a white-on-orange bar
 * inside the page's own layout instead -- a new section right after the
 * Source header and the date/volume line, before the content -- so the
 * layouts keep their full width. Section carries the class
 * cas-issue-menubar (styled in manzanita) and the label 'issue menu
 * (context)' for idempotent reconciliation.
 *
 * Usage: drush scr scripts-dev/place_source_issue_menus.php
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

// menu name => alias LIKE patterns (D7 context paths).
$issues = [
  'menu-spring-2023' => ['thesource/spring-2023%'],
  'menu-summer-2023' => ['thesource/summer-2023%'],
  'menu-fall-2023' => ['thesource/fall-2023%'],
  'menu-winter-2024' => ['thesource/winter-2024%'],
  'menu-spring-2024' => ['thesource/spring-2024%'],
  'menu-summer-2024' => ['thesource/summer-2024%'],
  'menu-fall-2024' => ['thesource/fall-2024%', 'thesource/2024-is4%', 'thesource/2024-iv%', 'thesource/2024-dec%'],
  // D7's menu for the March 2025 issue is (mis)named menu-march-2024.
  'menu-march-2024' => ['thesource/march-2025%'],
  'menu-june-2025' => ['thesource/june-2025%'],
  'menu-september-2025' => ['thesource/sept-2025%', 'thesource/september-2025%'],
  'menu-december-2025' => ['thesource/dec-2025%', 'thesource/december-2025%'],
  'menu-march-2026' => ['thesource/march-2026%'],
  'menu-june-2026' => ['thesource/june-2026%'],
  'menu-september-2026' => ['thesource/september-2026%', 'thesource/october-2026%'],
];

$db = \Drupal::database();
$storage = \Drupal::entityTypeManager()->getStorage('node');
$block_storage = \Drupal::entityTypeManager()->getStorage('block_content');
$menu_storage = \Drupal::entityTypeManager()->getStorage('menu');
$uuid = \Drupal::service('uuid');
$renderer = \Drupal::service('renderer');
$view_builder = \Drupal::entityTypeManager()->getViewBuilder('block_content');
$source_header_uuid = 'c3c09bfb-b697-4468-914b-098ff3c75469';

$placed = $skipped = 0;
foreach ($issues as $menu => $patterns) {
  if (!$menu_storage->load($menu)) {
    print "SKIP $menu: menu missing\n";
    continue;
  }
  $nids = [];
  foreach ($patterns as $p) {
    foreach ($db->query("SELECT DISTINCT path FROM {path_alias} WHERE alias LIKE :a", [':a' => '/' . $p])->fetchCol() as $path) {
      if (preg_match('~^/node/(\d+)$~', $path, $m)) {
        $nids[(int) $m[1]] = 1;
      }
    }
  }
  foreach (array_keys($nids) as $nid) {
    $node = $storage->load($nid);
    if (!$node || !$node->hasField('layout_builder__layout')) {
      $skipped++;
      continue;
    }
    $list = $node->get('layout_builder__layout');

    // Reconcile: drop a previously placed issue-menu section.
    for ($si = count($list->getSections()) - 1; $si >= 0; $si--) {
      if (($list->getSections()[$si]->getLayoutSettings()['label'] ?? '') === 'issue menu (context)') {
        $list->removeSection($si);
      }
    }

    // Insert after the date/volume line (a paragraph block whose text has
    // "Volume"), else after the Source header block, else at the top.
    $insert_at = 0;
    foreach ($list->getSections() as $si => $section) {
      foreach ($section->getComponents() as $c) {
        $cfg = $c->get('configuration');
        if (($cfg['id'] ?? '') === 'block_content:' . $source_header_uuid) {
          $insert_at = max($insert_at, $si + 1);
        }
        if (($cfg['id'] ?? '') === 'inline_block:paragraph_block' && !empty($cfg['block_revision_id']) && $si <= 3) {
          $b = $block_storage->loadRevision($cfg['block_revision_id']);
          if ($b) {
            $text = strip_tags((string) $renderer->renderPlain($view_builder->view($b)));
            if (stripos($text, 'Volume') !== FALSE) {
              $insert_at = max($insert_at, $si + 1);
            }
          }
        }
      }
    }

    $section = new Section('bootstrap_layout_builder:blb_col_1', [
      'label' => 'issue menu (context)', 'label_display' => 0,
      'container' => 'container', 'container_wrapper_classes' => '',
      'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '',
      'container_wrapper_bg_media' => NULL, 'section_classes' => 'cas-issue-menubar',
      'regions_classes' => ['blb_region_col_1' => ''], 'regions_attributes' => ['blb_region_col_1' => []],
      'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0',
    ]);
    $section->appendComponent(SectionComponent::fromArray([
      'uuid' => $uuid->generate(),
      'region' => 'blb_region_col_1',
      'configuration' => [
        'id' => 'system_menu_block:' . $menu,
        'label' => $menu_storage->load($menu)->label(),
        'provider' => 'system',
        'label_display' => '0',
        'level' => 1,
        'depth' => 0,
        'expand_all_items' => FALSE,
        'context_mapping' => [],
        'placement' => 'context',
      ],
      'additional' => [],
      'weight' => 0,
    ]));
    $list->insertSection($insert_at, $section);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    $placed++;
  }
}
printf("Placed: %d  Skipped: %d\n", $placed, $skipped);
