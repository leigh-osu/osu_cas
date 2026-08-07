<?php

/**
 * @file
 * One-off: fix styled columns on migrated 2-col layouts.
 *
 * Covers colored columns (orange & black) and background-image columns:
 *
 * D7 larch's orange-bg-left / orange-bg-right styles colored one column of
 * paragraph_2_col orange (#d73f09, white text) exactly like the black-bg-*
 * pair, but CasLayoutBase only handled black until 2026-08-06. The plugin
 * now covers both; this backfills existing layouts: every
 * inline_block:paragraph_block component whose block carries an orange-bg
 * style and sits in the styled column gets the same bootstrap_styles the
 * migration would now write. Idempotent.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_orange_bg_columns.php
 */

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;

$db = Database::getConnection();

// Block revision id -> styles value, for every paragraph_block revision
// with an orange-bg style.
$styles_by_revision = $db->query("SELECT revision_id, field_styles_value FROM block_content_revision__field_styles WHERE bundle='paragraph_block' AND (field_styles_value LIKE '%orange-bg-le%' OR field_styles_value LIKE '%orange-bg-ri%' OR field_styles_value LIKE '%black-bg-le%' OR field_styles_value LIKE '%black-bg-ri%')")->fetchAllKeyed();
print count($styles_by_revision) . " color-styled block revisions\n";

// Block revisions that carry body text (used to decide whether a
// background-image block keeps its translucent text box).
$bodied_revisions = $db->query("SELECT revision_id, 1 FROM block_content_revision__body WHERE bundle='paragraph_block' AND body_value IS NOT NULL AND body_value != ''")->fetchAllKeyed();

$settings_for = function (string $bg_class): array {
  return [
    'block_style' => [
      'background' => ['background_type' => 'color'],
      'background_color' => ['class' => $bg_class],
      'text_color' => ['class' => 'osu-text-bucktoothwhite'],
    ],
  ];
};

// Colour fills the full row height like D7's span backgrounds.
$h100_attributes = [
  'block_attributes' => [
    'id' => '',
    'class' => 'h-100',
    'style' => '',
    'data' => '',
  ],
];

$nids = $db->query("SELECT DISTINCT entity_id FROM node__layout_builder__layout WHERE layout_builder__layout_section LIKE '%inline_block:paragraph_block%'")->fetchCol();
$updated_nodes = $updated_components = 0;
foreach ($nids as $nid) {
  $node = Node::load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    continue;
  }
  $changed = FALSE;
  foreach ($node->get('layout_builder__layout')->getSections() as $section) {
    foreach ($section->getComponents() as $component) {
      if ($component->getPluginId() !== 'inline_block:paragraph_block') {
        continue;
      }
      $config = $component->get('configuration');
      $revision_id = $config['block_revision_id'] ?? NULL;
      if (!$revision_id) {
        continue;
      }
      $region = $component->getRegion();

      // Background-image columns (paragraph_2_col with entity background):
      // flush to the column edge, and no translucent box when the block has
      // no body text (it rendered as an empty white strip on the image).
      $comp_styles = $component->get('bootstrap_styles')['block_style'] ?? [];
      if (($comp_styles['background']['background_type'] ?? NULL) === 'image' && in_array($region, ['blb_region_col_1', 'blb_region_col_2'], TRUE)) {
        $layout_settings = $section->getLayoutSettings();
        if (!in_array('px-0', $layout_settings['layout_regions_classes'][$region] ?? [], TRUE)) {
          $layout_settings['layout_regions_classes'][$region][] = 'px-0';
          $section->setLayoutSettings($layout_settings);
          $changed = TRUE;
        }
        $attrs = $component->get('component_attributes') ?? [];
        if (($attrs['block_content_attributes']['class'] ?? '') === 'osu-bg-trans-white p-3'
          && empty($bodied_revisions[$revision_id])) {
          $attrs['block_content_attributes']['class'] = '';
          $component->set('component_attributes', $attrs);
          $changed = TRUE;
          $updated_components++;
        }
      }

      if (!isset($styles_by_revision[$revision_id])) {
        continue;
      }
      $styles = $styles_by_revision[$revision_id];
      $bg_class = NULL;
      if (($region === 'blb_region_col_1' && str_contains($styles, 'black-bg-left'))
        || ($region === 'blb_region_col_2' && str_contains($styles, 'black-bg-right'))) {
        $bg_class = 'osu-bg-page-alt-2';
      }
      elseif (($region === 'blb_region_col_1' && str_contains($styles, 'orange-bg-left'))
        || ($region === 'blb_region_col_2' && str_contains($styles, 'orange-bg-right'))) {
        $bg_class = 'osu-bg-osuorange';
      }
      if ($bg_class === NULL) {
        continue;
      }
      $block_settings = $settings_for($bg_class);
      $current = $component->get('bootstrap_styles') ?? [];
      // The colour paints the whole column in D7: drop the column's
      // horizontal gutter so it reaches the column edges.
      $layout_settings = $section->getLayoutSettings();
      if (!in_array('px-0', $layout_settings['layout_regions_classes'][$region] ?? [], TRUE)) {
        $layout_settings['layout_regions_classes'][$region][] = 'px-0';
        $section->setLayoutSettings($layout_settings);
        $changed = TRUE;
      }
      // The old migration ALSO painted the whole section dark for black-bg
      // styles; D7 darkens only the one column. Strip the section-level
      // colour so the other column stays on the page background.
      $layout_settings = $section->getLayoutSettings();
      if (($layout_settings['container_wrapper']['bootstrap_styles']['background_color']['class'] ?? NULL) === 'osu-bg-page-alt-2') {
        unset($layout_settings['container_wrapper']['bootstrap_styles']['background_color']);
        unset($layout_settings['container_wrapper']['bootstrap_styles']['text_color']);
        unset($layout_settings['container_wrapper']['bootstrap_styles']['background']);
        $section->setLayoutSettings($layout_settings);
        $changed = TRUE;
      }
      $current_attrs = $component->get('component_attributes') ?? [];
      if (($current_attrs['block_attributes']['class'] ?? '') !== 'h-100') {
        $component->set('component_attributes', $h100_attributes + $current_attrs);
        $changed = TRUE;
      }
      if ($current === $block_settings) {
        continue;
      }
      $component->set('bootstrap_styles', $block_settings);
      $changed = TRUE;
      $updated_components++;
    }
  }
  if ($changed) {
    $node->save();
    $updated_nodes++;
  }
}
print "Done: $updated_components components on $updated_nodes nodes.\n";
