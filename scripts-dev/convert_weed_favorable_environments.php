<?php

/**
 * @file
 * One-off: rebuild field_weed_favorable_environment as a multi-value list.
 *
 * D7's field_favorable_environments is a multi-value list_text with labels
 * (Container / Field / Aquatic / Greenhouse); the D10 field was created as
 * single-value text_long, so nodes kept only the first raw machine key
 * ("environment_container") and rendered it verbatim. Recreate the field
 * as list_string (unlimited) with the D7 allowed values, restore it into
 * the form and view displays, refill every weed from D7 by nid, and
 * rewrite the tracked config copies. The cas_weed_to_weed mapping is
 * simplified separately. Destructive only to the already-junk D10 values.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/convert_weed_favorable_environments.php
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$field_name = 'field_weed_favorable_environment';
$allowed = [
  'environment_container' => 'Container',
  'environment_field' => 'Field',
  'environment_aquatic' => 'Aquatic',
  'environment_greenhouse' => 'Greenhouse',
];

$efd = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.weed.default');
$evd = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.weed.default');
$old_view = $evd->getComponent($field_name);
$old_form = $efd->getComponent($field_name);

// Drop the mis-shaped field (storage delete cascades to the instance).
if ($storage = FieldStorageConfig::loadByName('node', $field_name)) {
  $storage->delete();
  print "old text_long field deleted\n";
}
field_purge_batch(50);

// Recreate as an unlimited list with the D7 values.
FieldStorageConfig::create([
  'field_name' => $field_name,
  'entity_type' => 'node',
  'type' => 'list_string',
  'cardinality' => -1,
  'settings' => ['allowed_values' => $allowed],
])->save();
FieldConfig::create([
  'field_name' => $field_name,
  'entity_type' => 'node',
  'bundle' => 'weed',
  'label' => 'Favorable Environments',
])->save();
print "recreated as list_string (unlimited)\n";

// Restore display placement at the old weights.
$efd = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.weed.default');
$efd->setComponent($field_name, [
  'type' => 'options_buttons',
  'weight' => $old_form['weight'] ?? 129,
  'region' => 'content',
  'settings' => [],
  'third_party_settings' => [],
]);
$efd->save();
$evd = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.weed.default');
$evd->setComponent($field_name, [
  'type' => 'list_default',
  'label' => $old_view['label'] ?? 'inline',
  'weight' => $old_view['weight'] ?? 10,
  'region' => 'content',
  'settings' => [],
  'third_party_settings' => [],
]);
$evd->save();
print "form + view displays restored\n";

// Refill from D7 (nids preserved).
$db = Database::getConnection();
$mig = Database::getConnection('default', 'migrate');
$rows = $mig->query(
  "SELECT f.entity_id, f.delta, f.field_favorable_environments_value v
   FROM field_data_field_favorable_environments f
   WHERE f.entity_type = 'node' AND f.bundle = 'weed'
   ORDER BY f.entity_id, f.delta"
)->fetchAll();
$filled = 0;
foreach ($rows as $row) {
  $vid = $db->query("SELECT vid FROM node_field_data WHERE nid = :n AND type = 'weed'", [':n' => $row->entity_id])->fetchField();
  if (!$vid) {
    print "nid {$row->entity_id}: no D10 weed node, skipped\n";
    continue;
  }
  $fields = [
    'bundle' => 'weed',
    'deleted' => 0,
    'entity_id' => $row->entity_id,
    'revision_id' => $vid,
    'langcode' => 'en',
    'delta' => $row->delta,
    "{$field_name}_value" => $row->v,
  ];
  $db->insert("node__$field_name")->fields($fields)->execute();
  $db->insert("node_revision__$field_name")->fields($fields)->execute();
  $filled++;
}
print "refilled $filled values from D7\n";

// Rewrite the tracked config copies.
$exports = [
  "field.storage.node.$field_name" => ['config/agsci-oregonstate-edu', 'config_imports/storage'],
  "field.field.node.weed.$field_name" => ['config/agsci-oregonstate-edu', 'config_imports/fields'],
  'core.entity_form_display.node.weed.default' => ['config/agsci-oregonstate-edu', 'config_imports/display'],
  'core.entity_view_display.node.weed.default' => ['config/agsci-oregonstate-edu', 'config_imports/display'],
];
foreach ($exports as $name => $dirs) {
  $raw = \Drupal::config($name)->getRawData();
  unset($raw['_core']);
  foreach ($dirs as $dir) {
    $path = DRUPAL_ROOT . "/../$dir/$name.yml";
    if (file_exists($path) || str_starts_with($dir, 'config/')) {
      file_put_contents($path, Yaml::encode($raw));
      print "$dir/$name.yml written\n";
    }
    else {
      print "$dir/$name.yml absent, skipped\n";
    }
  }
}
print "Done.\n";
