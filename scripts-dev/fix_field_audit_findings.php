<?php

/**
 * @file
 * Post-rebuild fix for the 2026-08-07 field-migration audit findings.
 *
 * - Four D10 fields were created single-value although their D7 sources
 *   are unlimited (field_dfs_location, field_course_class_location,
 *   field_vid_presenter, field_dfs_degree_focus_area): widen to unlimited
 *   and refill the dropped extra values from D7 by preserved nid. The
 *   migration mappings are plain `get`, so future rebuilds carry all
 *   values once the storage allows it — this script then only repairs
 *   DBs built before the widening.
 * - field_150_species_type is mapped by cas_150_species_to_150_species
 *   but never existed in D10 config: create it (term reference to the
 *   150_species vocabulary, D7 tids are preserved), place it on the form
 *   and view displays, and backfill the 165 D7 values.
 * - Tracked config copies (config/ + config_imports) are rewritten for
 *   every touched object. Idempotent.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_field_audit_findings.php
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$db = Database::getConnection();
$mig = Database::getConnection('default', 'migrate');
$exports = [];

// ---- 1. widen single-value fields whose D7 source is unlimited ------------
$widen = [
  // d10 field => [d7 field, d7 bundles, d10 bundle]
  'field_dfs_location' => ['field_location_dfs', ['degree_fact_sheet', 'degree_fact_sheet_graduate'], 'degree_fact_sheet'],
  'field_dfs_degree_focus_area' => ['field_degree_focus_area', ['degree_fact_sheet', 'degree_fact_sheet_graduate'], 'degree_fact_sheet'],
  'field_course_class_location' => ['field_class_location', ['course'], 'course'],
  'field_vid_presenter' => ['field_presenter', ['video'], 'video'],
];
foreach ($widen as $d10_field => [$d7_field, $d7_bundles, $d10_bundle]) {
  $storage = FieldStorageConfig::loadByName('node', $d10_field);
  if (!$storage) {
    print "$d10_field: storage missing, skipped\n";
    continue;
  }
  if ($storage->getCardinality() !== -1) {
    $storage->setCardinality(-1);
    $storage->save();
    print "$d10_field: cardinality -> unlimited\n";
  }
  $exports["field.storage.node.$d10_field"] = ['config/agsci.oregonstate.edu', 'config_imports/storage'];

  // Refill missing deltas from D7 (delta 0 is already there).
  $placeholders = "'" . implode("','", $d7_bundles) . "'";
  $rows = $mig->query(
    "SELECT entity_id, delta, {$d7_field}_value v FROM field_data_{$d7_field}
     WHERE entity_type = 'node' AND bundle IN ($placeholders) AND delta > 0
     ORDER BY entity_id, delta"
  )->fetchAll();
  $added = 0;
  foreach ($rows as $row) {
    $node = $db->query("SELECT vid, type FROM node_field_data WHERE nid = :n", [':n' => $row->entity_id])->fetch();
    if (!$node || $node->type !== $d10_bundle) {
      continue;
    }
    $have = $db->query("SELECT COUNT(*) FROM {node__$d10_field} WHERE entity_id = :n AND delta = :d", [':n' => $row->entity_id, ':d' => $row->delta])->fetchField();
    if ($have) {
      continue;
    }
    $fields = [
      'bundle' => $d10_bundle,
      'deleted' => 0,
      'entity_id' => $row->entity_id,
      'revision_id' => $node->vid,
      'langcode' => 'en',
      'delta' => $row->delta,
      "{$d10_field}_value" => $row->v,
    ];
    $db->insert("node__$d10_field")->fields($fields)->execute();
    $db->insert("node_revision__$d10_field")->fields($fields)->execute();
    $added++;
  }
  print "$d10_field: refilled $added dropped values from D7\n";
}

// ---- 2. create + backfill field_150_species_type --------------------------
if (!FieldStorageConfig::loadByName('node', 'field_150_species_type')) {
  FieldStorageConfig::create([
    'field_name' => 'field_150_species_type',
    'entity_type' => 'node',
    'type' => 'entity_reference',
    'cardinality' => 1,
    'settings' => ['target_type' => 'taxonomy_term'],
  ])->save();
  FieldConfig::create([
    'field_name' => 'field_150_species_type',
    'entity_type' => 'node',
    'bundle' => '150_species',
    'label' => 'Species Type',
    'settings' => [
      'handler' => 'default:taxonomy_term',
      'handler_settings' => ['target_bundles' => ['150_species' => '150_species']],
    ],
  ])->save();
  print "field_150_species_type created\n";
}
$efd = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.150_species.default');
if ($efd && !$efd->getComponent('field_150_species_type')) {
  $efd->setComponent('field_150_species_type', [
    'type' => 'options_select',
    'weight' => 5,
    'region' => 'content',
    'settings' => [],
    'third_party_settings' => [],
  ]);
  $efd->save();
}
$evd = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.150_species.default');
if ($evd && !$evd->getComponent('field_150_species_type')) {
  $evd->setComponent('field_150_species_type', [
    'type' => 'entity_reference_label',
    'label' => 'inline',
    'weight' => 5,
    'region' => 'content',
    'settings' => ['link' => TRUE],
    'third_party_settings' => [],
  ]);
  $evd->save();
}
$exports['field.storage.node.field_150_species_type'] = ['config/agsci.oregonstate.edu', 'config_imports/storage'];
$exports['field.field.node.150_species.field_150_species_type'] = ['config/agsci.oregonstate.edu', 'config_imports/fields'];
$exports['core.entity_form_display.node.150_species.default'] = ['config/agsci.oregonstate.edu', 'config_imports/display'];
$exports['core.entity_view_display.node.150_species.default'] = ['config/agsci.oregonstate.edu', 'config_imports/display'];

// Backfill from D7 (tids preserved; skip terms that did not migrate).
$rows = $mig->query(
  "SELECT entity_id, field_species_type_tid tid FROM field_data_field_species_type
   WHERE entity_type = 'node' AND bundle = '150_species'"
)->fetchAll();
$added = 0;
foreach ($rows as $row) {
  $node = $db->query("SELECT vid, type FROM node_field_data WHERE nid = :n", [':n' => $row->entity_id])->fetch();
  if (!$node || $node->type !== '150_species') {
    continue;
  }
  if ($db->query("SELECT COUNT(*) FROM {node__field_150_species_type} WHERE entity_id = :n", [':n' => $row->entity_id])->fetchField()) {
    continue;
  }
  if (!$db->query("SELECT COUNT(*) FROM taxonomy_term_field_data WHERE tid = :t", [':t' => $row->tid])->fetchField()) {
    print "  nid {$row->entity_id}: term {$row->tid} not in D10, skipped\n";
    continue;
  }
  $fields = [
    'bundle' => '150_species',
    'deleted' => 0,
    'entity_id' => $row->entity_id,
    'revision_id' => $node->vid,
    'langcode' => 'en',
    'delta' => 0,
    'field_150_species_type_target_id' => $row->tid,
  ];
  $db->insert('node__field_150_species_type')->fields($fields)->execute();
  $db->insert('node_revision__field_150_species_type')->fields($fields)->execute();
  $added++;
}
print "field_150_species_type: backfilled $added of " . count($rows) . " D7 values\n";

// ---- 3. rewrite tracked config copies -------------------------------------
foreach ($exports as $name => $dirs) {
  $raw = \Drupal::config($name)->getRawData();
  if (!$raw) {
    print "$name: no live object, export skipped\n";
    continue;
  }
  unset($raw['_core']);
  foreach ($dirs as $dir) {
    $path = DRUPAL_ROOT . "/../$dir/$name.yml";
    file_put_contents($path, Yaml::encode($raw));
  }
  print "exported $name\n";
}
print "Done.\n";
