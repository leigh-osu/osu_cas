<?php

/**
 * @file
 * One-off: set p-2 padding on every Layout Builder inline osu_accordion
 * block component (migrated blocks got the p-4-5 default). Matches the
 * CasLayoutBase change that makes p-2 the migration default for accordions.
 * Safe to re-run.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/set_accordion_padding.php
 */

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;

$db = Database::getConnection();
$nids = $db->query("SELECT DISTINCT entity_id FROM node__layout_builder__layout WHERE layout_builder__layout_section LIKE '%inline_block:osu_accordion%'")->fetchCol();
print count($nids) . " nodes with accordion blocks\n";

$updated_nodes = 0;
$updated_components = 0;
foreach ($nids as $nid) {
  $node = Node::load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    continue;
  }
  $changed = FALSE;
  $sections = $node->get('layout_builder__layout')->getSections();
  foreach ($sections as $section) {
    foreach ($section->getComponents() as $component) {
      if ($component->getPluginId() !== 'inline_block:osu_accordion') {
        continue;
      }
      $additional = $component->get('bootstrap_styles') ?? [];
      if (($additional['block_style']['padding']['class'] ?? NULL) !== 'p-2') {
        $additional['block_style']['padding']['class'] = 'p-2';
        $component->set('bootstrap_styles', $additional);
        $changed = TRUE;
        $updated_components++;
      }
    }
  }
  if ($changed) {
    $node->save();
    $updated_nodes++;
    print "OK $nid ({$node->label()})\n";
  }
}
print "Done: $updated_components components on $updated_nodes nodes.\n";
