<?php

/**
 * @file
 * One-off: default menu bars are orange like D7.
 *
 * D7's paragraph-menu default background is #d73f09 with white links; the
 * menu-* classes are overrides. The migration used to give unstyled menus
 * osu-bg-page-default (white) — setMenuBgClass now defaults to orange; this
 * repaints existing menu sections still carrying the white default.
 * Idempotent.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_menu_default_bg.php
 */

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;

$db = Database::getConnection();
$nids = $db->query("SELECT DISTINCT entity_id FROM node__layout_builder__layout WHERE layout_builder__layout_section LIKE '%inline_block:osu_menu_bar_item%'")->fetchCol();
print count($nids) . " nodes with menu bars\n";

$updated_nodes = $updated_sections = 0;
foreach ($nids as $nid) {
  $node = Node::load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    continue;
  }
  $changed = FALSE;
  foreach ($node->get('layout_builder__layout')->getSections() as $section) {
    $components = $section->getComponents();
    if (!$components) {
      continue;
    }
    foreach ($components as $component) {
      if ($component->getPluginId() !== 'inline_block:osu_menu_bar_item') {
        continue 2;
      }
    }
    $settings = $section->getLayoutSettings();
    if (($settings['container_wrapper']['bootstrap_styles']['background_color']['class'] ?? NULL) === 'osu-bg-page-default') {
      $settings['container_wrapper']['bootstrap_styles']['background_color']['class'] = 'osu-bg-osuorange';
      $settings['container_wrapper']['bootstrap_styles']['text_color']['class'] = 'osu-text-bucktoothwhite';
      $section->setLayoutSettings($settings);
      $changed = TRUE;
      $updated_sections++;
    }
  }
  if ($changed) {
    $node->save();
    $updated_nodes++;
  }
}
print "Done: $updated_sections menu sections on $updated_nodes nodes.\n";
