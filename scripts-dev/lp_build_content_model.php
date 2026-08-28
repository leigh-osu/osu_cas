<?php

/**
 * @file
 * Build the Landscape Plants content model on the landscapeplants site.
 *
 * One-shot, idempotent. Mirrors the D7 plantid7 field model exactly (field
 * names, cardinality, lengths, allowed values, vocabulary targets), per the
 * migration audit. Run with:
 *   ddev drush --uri=https://ddev.landscapeplants.oregonstate.edu \
 *     php:script scripts-dev/lp_build_content_model.php
 * then `cex` the result.
 */

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\editor\Entity\Editor;

// -- Vocabularies (D7 machine names and labels, verbatim). -----------------
$vocabularies = [
  'term_genus' => 'Genus',
  'term_features' => 'Features',
  'technical_terms' => 'Technical Terms',
  'term_features_conifer' => 'Conifer features',
  'term_features_broadleaf' => 'Broad Leaf features',
  'con_leaf_shape_group' => 'Leaf shape and grouping',
  'bl_flower_color' => 'Flower color',
  'bl_fruit_color' => 'Fruit color',
  'bl_leaf_color' => 'Leaf color (growing season)',
  'con_leaf_color' => 'Leaf color (growing season)',
  'plant_bl_leaf_or_leaflets_chars' => 'Leaf or leaflets characteristics',
  'bl_fruit_shape' => 'Fruit shape',
  'bl_leaf_types' => 'Leaf types',
  'con_fruit' => '"Fruit" (cone)',
  'con_growth_form' => 'Growth form',
  'bl_leaf_attachment' => 'Leaf attachment to stem',
  'bl_growth_habit' => 'Growth habit',
  'bl_stems' => 'Stems',
  'bl_leaf_persistence' => 'Leaf persistence',
  'con_leaf_persistence' => 'Leaf persistence',
  'con_growth_habit' => 'Growth habit',
  'bl_flower_appearance' => 'Flower appearance',
  'con_stems' => 'Stems',
  'common_names' => 'Common Name List',
];
foreach ($vocabularies as $vid => $label) {
  if (!Vocabulary::load($vid)) {
    Vocabulary::create(['vid' => $vid, 'name' => $label])->save();
    print "vocab $vid\n";
  }
}

// -- Content types. ---------------------------------------------------------
$types = [
  'plant' => ['name' => 'Plant', 'description' => 'A woody plant record in the identification reference.'],
  'page' => ['name' => 'Basic page', 'description' => 'Static content such as the About page and reference articles.'],
];
foreach ($types as $type => $info) {
  if (!NodeType::load($type)) {
    NodeType::create([
      'type' => $type,
      'name' => $info['name'],
      'description' => $info['description'],
      'display_submitted' => FALSE,
    ])->save();
    print "type $type\n";
  }
}

// -- Text formats (D7 parity: no tag restrictions, htmlcorrector only). -----
$formats = [
  'filtered_html' => 'Filtered HTML',
  'full_html' => 'Full HTML',
];
foreach ($formats as $format => $label) {
  if (!FilterFormat::load($format)) {
    FilterFormat::create([
      'format' => $format,
      'name' => $label,
      'weight' => $format === 'filtered_html' ? 0 : 1,
      'filters' => [
        'filter_htmlcorrector' => ['status' => TRUE, 'weight' => 10],
      ],
    ])->save();
    Editor::create([
      'format' => $format,
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => [
            'bold', 'italic', 'link', '|',
            'bulletedList', 'numberedList', '|',
            'blockQuote', 'insertTable', 'sourceEditing',
          ],
        ],
        'plugins' => [
          'ckeditor5_sourceEditing' => ['allowed_tags' => []],
        ],
      ],
      'image_upload' => ['status' => FALSE],
    ])->save();
    print "format $format\n";
  }
}

// -- Field definitions. -----------------------------------------------------
// [bundle, name, storage type, cardinality, label, required, storage settings,
//  field settings]
$term_ref = function ($vocab) {
  return [
    ['target_type' => 'taxonomy_term'],
    ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => [$vocab => $vocab]]],
  ];
};
$fields = [
  // Plant: name parts.
  ['plant', 'field_plant_genus', 'entity_reference', 1, 'Genus', FALSE] + [6 => $term_ref('term_genus')],
  ['plant', 'field_plant_specific_epithet', 'string', 1, 'Specific Epithet', FALSE, ['max_length' => 255]],
  ['plant', 'field_plant_variety', 'string', 1, 'Variety', FALSE, ['max_length' => 255]],
  ['plant', 'field_plant_subspecies', 'string', 1, 'Subspecies', FALSE, ['max_length' => 255]],
  ['plant', 'field_plant_form', 'string', 1, 'Forma', FALSE, ['max_length' => 255]],
  ['plant', 'field_plant_unranked', 'string', 1, 'Unranked', FALSE, ['max_length' => 60]],
  ['plant', 'field_plant_cultivar', 'string', 1, 'Cultivar/Trademark', FALSE, ['max_length' => 255]],
  ['plant', 'field_plant_hybrid', 'boolean', 1, 'Hybrid', TRUE, [], ['on_label' => 'Yes', 'off_label' => 'No']],
  ['plant', 'field_plant_trademark', 'list_string', 1, 'Trademark', TRUE, ['allowed_values' => ['0' => 'none', 'tm' => 'Unregistered ™', 'r' => 'Registered ®']]],
  ['plant', 'field_plant_latin_name', 'string', 1, 'Latin name', FALSE, ['max_length' => 255]],
  ['plant', 'field_plant_family', 'string', 1, 'Family', FALSE, ['max_length' => 255]],
  ['plant', 'field_plant_pronunciation', 'string', 1, 'Pronunciation', FALSE, ['max_length' => 140]],
  ['plant', 'field_plant_common_name', 'string', -1, 'Common name', FALSE, ['max_length' => 255]],
  ['plant', 'field_plant_common_name_parent', 'string', -1, 'Common name parent', FALSE, ['max_length' => 255]],
  ['plant', 'field_synonyms', 'string', -1, 'Synonyms', FALSE, ['max_length' => 255]],
  // Plant: computed names (stored; recomputed by osu_landscapeplants presave).
  ['plant', 'field_plant_formatted_name', 'text_long', 1, 'Formatted name', FALSE],
  ['plant', 'field_plant_sort_name', 'string', 1, 'Sort name', FALSE, ['max_length' => 255]],
  // Plant: classification and content.
  ['plant', 'field_plant_type', 'list_string', 1, 'Type', TRUE, ['allowed_values' => ['1' => 'Broadleaf', '2' => 'Conifer']]],
  ['plant', 'field_plant_native', 'boolean', 1, 'Native to (or naturalized in) Oregon', TRUE, [], ['on_label' => 'Yes', 'off_label' => 'No']],
  ['plant', 'field_plant_description', 'text_long', 1, 'Description', FALSE],
  ['plant', 'field_plant_details', 'text_long', 1, 'Details', FALSE],
  ['plant', 'field_plant_original_text', 'text_long', 1, 'Original text', FALSE],
  ['plant', 'field_plant_original_info', 'text_long', -1, 'Original info', FALSE],
  ['plant', 'field_plant_review_state', 'list_string', 1, 'Review state', FALSE, ['allowed_values' => ['draft' => 'Draft', 'review' => 'Ready for review', 'complete' => 'Review complete']]],
  ['plant', 'field_plant_review_note', 'text_long', 1, 'Review notes', FALSE],
  ['plant', 'field_plant_urlref', 'link', 1, 'Old URL ref', FALSE, [], ['title' => 1, 'link_type' => 17]],
  ['plant', 'field_plant_images', 'image', -1, 'Images', FALSE, [], [
    'alt_field' => TRUE, 'alt_field_required' => FALSE, 'title_field' => TRUE,
    'file_directory' => 'plantimage', 'file_extensions' => 'png gif jpg jpeg',
  ]],
  ['plant', 'field_plant_features', 'entity_reference', -1, 'Old Features - reference only!!', FALSE] + [6 => $term_ref('term_features')],
  // Plant: broadleaf traits.
  ['plant', 'field_plant_bl_flower_appearance', 'entity_reference', -1, 'Flower appearance', FALSE] + [6 => $term_ref('bl_flower_appearance')],
  ['plant', 'field_plant_bl_flower_color', 'entity_reference', -1, 'Flower color', FALSE] + [6 => $term_ref('bl_flower_color')],
  ['plant', 'field_plant_bl_fruit_color', 'entity_reference', -1, 'Fruit color', FALSE] + [6 => $term_ref('bl_fruit_color')],
  ['plant', 'field_plant_bl_fruit_shape', 'entity_reference', -1, 'Fruit shape', FALSE] + [6 => $term_ref('bl_fruit_shape')],
  ['plant', 'field_plant_bl_growth_habit', 'entity_reference', -1, 'Growth habit', FALSE] + [6 => $term_ref('bl_growth_habit')],
  ['plant', 'field_plant_bl_leaf_attachment', 'entity_reference', -1, 'Leaf attachment to stem', FALSE] + [6 => $term_ref('bl_leaf_attachment')],
  ['plant', 'field_plant_bl_leaf_chars', 'entity_reference', -1, 'Leaf or leaflets characteristics', FALSE] + [6 => $term_ref('plant_bl_leaf_or_leaflets_chars')],
  ['plant', 'field_plant_bl_leaf_color', 'entity_reference', -1, 'Leaf color (growing season)', FALSE] + [6 => $term_ref('bl_leaf_color')],
  ['plant', 'field_plant_bl_leaf_persistence', 'entity_reference', -1, 'Leaf persistence', FALSE] + [6 => $term_ref('bl_leaf_persistence')],
  ['plant', 'field_plant_bl_leaf_types', 'entity_reference', -1, 'Leaf types', FALSE] + [6 => $term_ref('bl_leaf_types')],
  ['plant', 'field_plant_bl_stems', 'entity_reference', -1, 'Stems', FALSE] + [6 => $term_ref('bl_stems')],
  // Plant: conifer traits.
  ['plant', 'field_plant_con_fruit', 'entity_reference', -1, 'Fruit', FALSE] + [6 => $term_ref('con_fruit')],
  ['plant', 'field_plant_con_growth_form', 'entity_reference', -1, 'Growth form', FALSE] + [6 => $term_ref('con_growth_form')],
  ['plant', 'field_plant_con_growth_habit', 'entity_reference', -1, 'Growth habit', FALSE] + [6 => $term_ref('con_growth_habit')],
  ['plant', 'field_plant_con_leaf_color', 'entity_reference', -1, 'Leaf color (growing season)', FALSE] + [6 => $term_ref('con_leaf_color')],
  ['plant', 'field_plant_con_leaf_persistence', 'entity_reference', -1, 'Leaf persistence', FALSE] + [6 => $term_ref('con_leaf_persistence')],
  ['plant', 'field_plant_con_leaf_shape_group', 'entity_reference', -1, 'Leaf shape and grouping', FALSE] + [6 => $term_ref('con_leaf_shape_group')],
  ['plant', 'field_plant_con_stems', 'entity_reference', -1, 'Stems', FALSE] + [6 => $term_ref('con_stems')],
  // Page.
  ['page', 'body', 'text_with_summary', 1, 'Body', FALSE],
  ['page', 'field_page_image', 'image', -1, 'Image', FALSE, [], [
    'alt_field' => TRUE, 'alt_field_required' => FALSE, 'title_field' => FALSE,
    'file_extensions' => 'png gif jpg jpeg',
  ]],
  ['page', 'field_page_species_reference', 'entity_reference', 1, 'Species reference', FALSE, ['target_type' => 'node'], ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['plant' => 'plant']]]],
];

$display_repo = \Drupal::service('entity_display.repository');
foreach ($fields as $def) {
  [$bundle, $name, $type, $cardinality, $label, $required] = $def;
  $storage_settings = $def[6] ?? [];
  $field_settings = $def[7] ?? [];
  if ($type === 'entity_reference' && is_array($def[6] ?? NULL) && isset($def[6][0])) {
    // $term_ref() packs [storage settings, field settings].
    [$storage_settings, $field_settings] = $def[6];
  }
  $storage = FieldStorageConfig::loadByName('node', $name);
  if (!$storage) {
    $storage = FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $type,
      'cardinality' => $cardinality,
      'settings' => $storage_settings,
    ]);
    $storage->save();
  }
  if (!FieldConfig::loadByName('node', $bundle, $name)) {
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => $bundle,
      'label' => $label,
      'required' => $required,
      'settings' => $field_settings,
    ])->save();
    // Default widget and formatter; display polish is phase 3.
    $display_repo->getFormDisplay('node', $bundle)->setComponent($name)->save();
    $display_repo->getViewDisplay('node', $bundle)->setComponent($name, ['label' => 'above'])->save();
    print "field $bundle.$name\n";
  }
}

print "done\n";
