<?php

/**
 * Phase 3 of the field_lp_col_image repair: create the missing sections for
 * D7 adjustable-columns paragraphs whose section carrier failed entirely
 * (every column was image-only, so no sibling section exists to append to).
 *
 * The per-node plan below was derived by aligning D7 paragraph deltas with
 * the D10 section list (dividers produce empty sections; failed carriers
 * produce none). Items are listed in D7 delta order; weight = position.
 *
 * Node 285266 (story) is deliberately excluded — stories have no layout
 * overrides at all and its whole paragraph content is missing, a separate
 * problem from this repair.
 *
 * Usage:
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_lp_col_sections.php          (dry run)
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_lp_col_sections.php apply
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

$apply = isset($extra) && in_array('apply', $extra, TRUE);

// node => list of insertions (applied in the order given; indexes are
// positions in the node's CURRENT section list, so multiple insertions on
// one node are listed descending).
$plan = [
  251276 => [['index' => 8, 'donor' => 7, 'items' => [128131, 128136, 128141, 128146, 128241, 128246]]],
  251291 => [
    ['index' => 12, 'donor' => 6, 'items' => [128216]],
    ['index' => 10, 'donor' => 6, 'items' => [128186]],
    ['index' => 8, 'donor' => 6, 'items' => [128176]],
    ['index' => 6, 'donor' => 6, 'items' => [128156]],
  ],
  282501 => [['index' => 15, 'donor' => 14, 'items' => [130316, 130321, 130326, 130331]]],
  283526 => [['index' => 35, 'donor' => 34, 'items' => [128436]]],
  284956 => [['index' => 1, 'donor' => NULL, 'items' => [132486, 132491]]],
];

$default_settings = [
  'label' => '',
  'container_wrapper_classes' => '',
  'container_wrapper_attributes' => [],
  'container_wrapper' => ['bootstrap_styles' => []],
  'container_wrapper_bg_color_class' => '',
  'container_wrapper_bg_media' => NULL,
  'container' => 'container',
  'section_classes' => '',
  'section_attributes' => [],
  'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'],
  'regions_attributes' => ['blb_region_col_1' => []],
  'breakpoints' => [],
  'layout_regions_classes' => [],
];

$db = \Drupal::database();
$block_storage = \Drupal::entityTypeManager()->getStorage('block_content');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$uuid_service = \Drupal::service('uuid');

foreach ($plan as $nid => $insertions) {
  $node = $node_storage->load($nid);
  if (!$node) {
    print "node $nid: MISSING, skipped\n";
    continue;
  }
  $sections = $node->get('layout_builder__layout')->getSections();

  foreach ($insertions as $ins) {
    $settings = $default_settings;
    if ($ins['donor'] !== NULL && isset($sections[$ins['donor']])) {
      $settings = $sections[$ins['donor']]->getLayoutSettings();
      $settings['label'] = '';
    }

    $components = [];
    foreach ($ins['items'] as $weight => $item_id) {
      $block_id = (int) $db->query('SELECT destid1 FROM {migrate_map_field_collection_field_lp_adj_column__to__layout_bu} WHERE sourceid1 = :s', [':s' => $item_id])->fetchField();
      if (!$block_id) {
        print "node $nid item $item_id: NO BLOCK, component skipped\n";
        continue;
      }
      $block = $block_storage->load($block_id);
      $revision_id = $block_storage->getLatestRevisionId($block_id);

      $col = [];
      if ($raw = $block->get('field_block_serialized_data')->value) {
        $un = unserialize($raw);
        $col = $un['migration']['adjustable_columns_item'] ?? [];
      }
      $classes = [];
      $styles = [];
      foreach (['xs', 'sm', 'md', 'lg'] as $bp) {
        if (isset($col["field_lp_col_{$bp}_width"][0]['value'])) {
          $classes[] = "col-$bp-" . $col["field_lp_col_{$bp}_width"][0]['value'];
        }
        if (isset($col["field_lp_col_{$bp}_offset"][0]['value'])) {
          $classes[] = "offset-$bp-" . $col["field_lp_col_{$bp}_offset"][0]['value'];
        }
      }
      if (isset($col['field_lp_col_padding'][0]['value'])) {
        $v = $col['field_lp_col_padding'][0]['value'];
        $styles[] = str_contains($v, '{') ? $v : "padding: $v;";
      }
      foreach ($col['field_lp_col_class'] ?? [] as $v) {
        if (str_contains($v['value'], '{')) {
          $styles[] = $v['value'];
        }
        else {
          $classes[] = $v['value'];
        }
      }
      foreach ($col['field_lp_col_style'] ?? [] as $v) {
        $styles[] = $v['value'];
      }

      $additional = [];
      foreach (['block_attributes', 'block_title_attributes', 'block_content_attributes'] as $element) {
        $additional['component_attributes'][$element] = ['id' => '', 'class' => '', 'style' => '', 'data' => ''];
      }
      $additional['component_attributes']['block_attributes']['class'] = implode(' ', $classes);
      $additional['component_attributes']['block_attributes']['style'] = implode(' ', $styles);

      $components[] = SectionComponent::fromArray([
        'uuid' => $uuid_service->generate(),
        'region' => 'blb_region_col_1',
        'configuration' => [
          'id' => 'inline_block:paragraph_block',
          'label' => 'Layout Builder Inline Block',
          'provider' => 'layout_builder',
          'label_display' => '0',
          'view_mode' => 'full',
          'block_revision_id' => $revision_id,
          'block_serialized' => NULL,
          'context_mapping' => [],
        ],
        'additional' => $additional,
        'weight' => $weight,
      ]);
      print ($apply ? 'PLACING' : 'WOULD PLACE') . " node $nid new-section@{$ins['index']} weight $weight item $item_id block $block_id rev $revision_id classes=\"" . implode(' ', $classes) . "\"\n";
    }

    if (!$components) {
      continue;
    }
    $new_section = new Section('bootstrap_layout_builder:blb_col_1', $settings, $components);
    array_splice($sections, $ins['index'], 0, [$new_section]);
  }

  if ($apply) {
    $node->set('layout_builder__layout', array_map(fn($s) => ['section' => $s], $sections));
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    print "node $nid saved (" . count($sections) . " sections)\n";
  }
}
print "done (" . ($apply ? 'APPLY' : 'DRY RUN') . ")\n";
