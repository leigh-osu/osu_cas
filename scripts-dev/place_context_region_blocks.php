<?php

/**
 * @file
 * Place the custom blocks and menus D7 contexts put in theme regions.
 *
 * The D7 context module's non-views tail: a few department side menus,
 * and hand-written custom blocks (the Malheur weather links, the
 * social-media button bar, EMT contacts, ...). Each
 * becomes a manzanita block config with request-path visibility copied
 * from the context's path condition. Region map: sidebar_first -> sidebar,
 * pre_content -> content (weight 0, between the page
 * title and the main content), content/post_content -> content (weight 5,
 * after the main content).
 *
 * Idempotent: every block it owns is prefixed manzanita_ctx_ and re-created
 * on each run.
 *
 * Usage: drush scr scripts-dev/place_context_region_blocks.php
 */

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;

$theme = 'manzanita';

// The Source issue menus are NOT sidebar blocks here: see
// place_source_issue_menus.php, which puts them into each issue page's layout
// as a horizontal bar under the Source header.
$emt_nodes = [217606, 217611, 217616, 217621, 217626, 217631, 217636, 217641, 217646, 217651, 217716, 217721, 217731, 217736, 217751, 217756, 217761, 217766, 217771, 217776, 217781, 217786, 217791, 217801, 217806, 217946, 217951, 218111, 219776, 219821, 212141, 225791];
$rafwe_paths = ['fwcs/rafwe*', 'node/44661', 'node/217061', 'node/217671', 'node/248716', 'node/230036', 'node/253331', 'node/217691', 'node/217681', 'node/217441', 'node/253466', 'node/253471', 'node/253476', 'node/253481'];

// id suffix => [kind, target, D7 region, weight, paths].
$specs = [];
$specs['menu_academics'] = ['menu', 'menu-academics', 'sidebar_first', -10, array_map(fn($n) => "node/$n", $emt_nodes)];
$specs['emt_ap_contacts'] = ['block_content', 336, 'sidebar_first', -9, array_map(fn($n) => "node/$n", $emt_nodes)];
$specs['menu_bioenergy_programs'] = ['menu', 'menu-bioenergy-programs', 'sidebar_first', -7, ['node/26047', 'node/26240']];
$specs['menu_rafwe'] = ['menu', 'menu-rafwe', 'sidebar_first', -10, $rafwe_paths];
$specs['nw_plant_eval_menu'] = ['block_content', 171, 'sidebar_first', -10, ['node/49351', 'node/49671', 'node/49711', 'node/49736', 'node/49741', 'node/49761']];
// Not placed: the MES onion/wildflower headers (blocks 276/271). Their D7
// contexts target a full_top region larch never had, so D7 never rendered
// them.
$specs['malheur_weather_links'] = ['block_content', 301, 'pre_content', -10, ['malheur/weather/yearly/*', 'malheur/weather/yearly-summary/*']];
// D7 matched social-media/* by alias; several of those nodes carry a newer
// canonical alias in D10, so also name them by system path.
$social_paths = ['social-media/*', 'main/social-media-and-multimedia', 'main/newsletters'];
$social_nids = \Drupal::database()->query("SELECT DISTINCT path FROM {path_alias} WHERE alias LIKE :a", [':a' => '/social-media/%'])->fetchCol();
foreach ($social_nids as $path) {
  $social_paths[] = ltrim($path, '/');
}
$specs['social_media_buttons'] = ['block_content', 206, 'content', -10, $social_paths];
$specs['bmsb_news'] = ['block_content', 96, 'content', -1, ['node/36676']];

$region_map = [
  'sidebar_first' => ['sidebar', 0],
  'pre_content' => ['content', 0],
  'content' => ['content', 5],
  'post_content' => ['content', 5],
];

// Reconcile: drop everything this script owns.
foreach (Block::loadMultiple() as $block) {
  if (strpos($block->id(), $theme . '_ctx_') === 0) {
    $block->delete();
  }
}

$placed = $skipped = 0;
foreach ($specs as $suffix => [$kind, $target, $d7_region, $d7_weight, $paths]) {
  [$region, $base_weight] = $region_map[$d7_region];
  if ($kind === 'menu') {
    if (!\Drupal::entityTypeManager()->getStorage('menu')->load($target)) {
      print "SKIP $suffix: menu $target missing\n";
      $skipped++;
      continue;
    }
    $plugin = 'system_menu_block:' . $target;
    $settings = ['id' => $plugin, 'label' => '', 'label_display' => 'visible', 'provider' => 'system', 'level' => 1, 'depth' => 0, 'expand_all_items' => FALSE];
    $settings['label'] = \Drupal::entityTypeManager()->getStorage('menu')->load($target)->label();
  }
  else {
    $bc = BlockContent::load($target);
    if (!$bc) {
      print "SKIP $suffix: block_content $target missing\n";
      $skipped++;
      continue;
    }
    $plugin = 'block_content:' . $bc->uuid();
    $settings = ['id' => $plugin, 'label' => $bc->label(), 'label_display' => '0', 'provider' => 'block_content', 'status' => TRUE, 'info' => '', 'view_mode' => 'full'];
  }
  $pages = implode("\n", array_map(fn($p) => '/' . ltrim($p, '/'), $paths));
  Block::create([
    'id' => $theme . '_ctx_' . str_replace('-', '_', $suffix),
    'theme' => $theme,
    'region' => $region,
    'weight' => $base_weight + (int) $d7_weight,
    'plugin' => $plugin,
    'settings' => $settings,
    'visibility' => [
      'request_path' => ['id' => 'request_path', 'negate' => FALSE, 'pages' => $pages],
    ],
  ])->save();
  $placed++;
}
printf("Placed: %d  Skipped: %d\n", $placed, $skipped);
