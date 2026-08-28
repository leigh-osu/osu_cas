<?php

/**
 * @file
 * Build the Landscape Plants facet set: 12 on the broadleaf search, 8 on the
 * conifer search, mirroring the D7 facetapi blocks — same titles, order
 * (term weight), soft limit 20, hard limit 50, AND operator, checkbox links,
 * and the D7 query-string alias (full field name) so old bookmarked
 * searches keep working. Idempotent.
 *
 *   ddev drush --uri=https://ddev.landscapeplants.oregonstate.edu \
 *     php:script scripts-dev/lp_build_facets.php
 */

use Drupal\facets\Entity\Facet;
use Drupal\block\Entity\Block;

$sets = [
  'bl' => [
    'source' => 'search_api:views_page__search_broadleaf__page_1',
    'facets' => [
      ['bl_native', 'field_plant_native', 'Filter by native to (or naturalized in) Oregon:', 'boolean'],
      ['bl_growth_habit', 'field_plant_bl_growth_habit', 'Filter by growth habit:', 'term'],
      ['bl_stems', 'field_plant_bl_stems', 'Filter by stems:', 'term'],
      ['bl_leaf_persistence', 'field_plant_bl_leaf_persistence', 'Filter by leaf persistence:', 'term'],
      ['bl_leaf_attachment', 'field_plant_bl_leaf_attachment', 'Filter by leaf attachment to stem:', 'term'],
      ['bl_leaf_color', 'field_plant_bl_leaf_color', 'Filter by leaf color (growing season):', 'term'],
      ['bl_leaf_types', 'field_plant_bl_leaf_types', 'Filter by leaf types:', 'term'],
      ['bl_leaf_chars', 'field_plant_bl_leaf_chars', 'Filter by leaf or leaflets characteristics:', 'term'],
      ['bl_flower_appearance', 'field_plant_bl_flower_appearance', 'Filter by flower appearance:', 'term'],
      ['bl_flower_color', 'field_plant_bl_flower_color', 'Filter by flower color:', 'term'],
      ['bl_fruit_shape', 'field_plant_bl_fruit_shape', 'Filter by fruit shape:', 'term'],
      ['bl_fruit_color', 'field_plant_bl_fruit_color', 'Filter by fruit color:', 'term'],
    ],
  ],
  'con' => [
    'source' => 'search_api:views_page__search_conifer__page_1',
    'facets' => [
      ['con_native', 'field_plant_native', 'Filter by native to (or naturalized in) Oregon:', 'boolean'],
      ['con_growth_habit', 'field_plant_con_growth_habit', 'Filter by growth habit:', 'term'],
      ['con_growth_form', 'field_plant_con_growth_form', 'Filter by growth form:', 'term'],
      ['con_stems', 'field_plant_con_stems', 'Filter by stems:', 'term'],
      ['con_leaf_persistence', 'field_plant_con_leaf_persistence', 'Filter by leaf persistence:', 'term'],
      ['con_leaf_color', 'field_plant_con_leaf_color', 'Filter by leaf color (growing season):', 'term'],
      ['con_leaf_shape_group', 'field_plant_con_leaf_shape_group', 'Filter by leaf shape and grouping:', 'term'],
      ['con_fruit', 'field_plant_con_fruit', 'Filter by “fruit” (cone)', 'term'],
    ],
  ],
];

foreach ($sets as $set) {
  $weight = -31;
  foreach ($set['facets'] as [$id, $field, $label, $kind]) {
    $weight++;
    if (!Facet::load($id)) {
      $facet = Facet::create([
        'id' => $id,
        'name' => $label,
        'url_alias' => $field,
        'field_identifier' => $field,
        'facet_source_id' => $set['source'],
        'query_operator' => 'and',
        'hard_limit' => 50,
        'min_count' => 1,
        'weight' => $weight,
        'only_visible_when_facet_source_is_visible' => TRUE,
        'widget' => ['type' => 'checkbox', 'config' => ['show_numbers' => TRUE, 'soft_limit' => 20,
          'soft_limit_settings' => ['show_less_label' => 'Show fewer', 'show_more_label' => 'Show more']]],
        'empty_behavior' => ['behavior' => 'text', 'text_format' => 'filtered_html', 'text' => '<em>No matches with selected filters.</em>'],
      ]);
      $facet->addProcessor(['processor_id' => 'url_processor_handler', 'weights' => ['pre_query' => -10, 'build' => -10], 'settings' => []]);
      if ($kind === 'term') {
        $facet->addProcessor(['processor_id' => 'translate_entity', 'weights' => ['build' => -20], 'settings' => []]);
        $facet->addProcessor(['processor_id' => 'term_weight_widget_order', 'weights' => ['sort' => -40], 'settings' => ['sort' => 'ASC']]);
      }
      else {
        // Yes before No, the D7 indexed-descending order.
        $facet->addProcessor(['processor_id' => 'boolean_item', 'weights' => ['build' => -20], 'settings' => ['on_value' => 'Yes', 'off_value' => 'No']]);
        $facet->addProcessor(['processor_id' => 'raw_value_widget_order', 'weights' => ['sort' => -40], 'settings' => ['sort' => 'DESC']]);
      }
      $facet->save();
      print "facet $id\n";
    }
    $block_id = 'lp_facet_' . $id;
    if (!Block::load($block_id)) {
      Block::create([
        'id' => $block_id,
        'theme' => 'manzanita',
        'region' => 'full_top',
        'weight' => $weight,
        'plugin' => 'facet_block:' . $id,
        'settings' => ['id' => 'facet_block:' . $id, 'label' => $label, 'label_display' => 'visible', 'provider' => 'facets'],
        'visibility' => [],
      ])->save();
      print "block $block_id\n";
    }
  }
}
print "done\n";
