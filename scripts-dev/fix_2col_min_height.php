<?php

/**
 * @file
 * One-off: drop the blanket section-level osu-min-h-600 from converted
 * 2-col paragraph sections.
 *
 * CasLayoutBase used to stamp min_height osu-min-h-600 on EVERY
 * paragraph_2_col section, but D7 sized those bands to their content —
 * short colored bands (black/orange-bg columns) rendered with big empty
 * areas here. The rule is now removed from the converter; this repairs
 * layouts already in the DB.
 *
 * A section keeps osu-min-h-600 when its own background is an image or
 * video (the paragraph_1_col / background-video converters set both
 * together, and there the height IS the design). Everything else loses
 * it. Block-level min-heights (image columns) are untouched. Idempotent.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_2col_min_height.php
 */

use Drupal\node\Entity\Node;

$db = \Drupal::database();

$nids = $db->query("SELECT DISTINCT entity_id FROM node__layout_builder__layout WHERE layout_builder__layout_section LIKE '%osu-min-h-600%'")->fetchCol();
print count($nids) . " candidate nodes\n";

$updated = 0;
$sections_fixed = 0;
foreach ($nids as $nid) {
  $node = Node::load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    continue;
  }
  $changed = FALSE;
  foreach ($node->get('layout_builder__layout')->getSections() as $section) {
    $settings = $section->getLayoutSettings();
    $styles = $settings['container_wrapper']['bootstrap_styles'] ?? [];
    if (($styles['min_height']['class'] ?? '') !== 'osu-min-h-600') {
      continue;
    }
    $bg_type = $styles['background']['background_type'] ?? '';
    if ($bg_type === 'image' || $bg_type === 'video') {
      continue;
    }
    unset($settings['container_wrapper']['bootstrap_styles']['min_height']);
    $section->setLayoutSettings($settings);
    $changed = TRUE;
    $sections_fixed++;
  }
  if ($changed) {
    $node->save();
    $updated++;
  }
}
print "Done: removed section min-height from $sections_fixed sections across $updated nodes.\n";
