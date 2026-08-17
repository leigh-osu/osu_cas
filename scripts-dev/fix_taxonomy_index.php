<?php

/**
 * @file
 * Rebuild taxonomy_index from every node term field.
 *
 * Migrated nodes largely missed taxonomy_index population (465 tagged
 * videos held 2 index rows), so tid-argument listings under-report.
 * Recomputes the whole table from the node__* term-reference field tables
 * for published nodes. Idempotent; runs each rebuild.
 *
 * Usage: drush scr scripts-dev/fix_taxonomy_index.php
 */

$db = \Drupal::database();
$efm = \Drupal::service('entity_field.manager');
$fields = [];
foreach ($efm->getFieldMapByFieldType('entity_reference')['node'] ?? [] as $name => $info) {
  $storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', $name);
  if ($storage && $storage->getSetting('target_type') === 'taxonomy_term') {
    $fields[] = $name;
  }
}
$before = (int) $db->query('SELECT COUNT(*) FROM {taxonomy_index}')->fetchField();
$db->truncate('taxonomy_index')->execute();
foreach ($fields as $name) {
  $table = 'node__' . $name;
  if (!$db->schema()->tableExists($table)) {
    continue;
  }
  $db->query("
    INSERT IGNORE INTO {taxonomy_index} (nid, tid, status, sticky, created)
    SELECT f.entity_id, f.{$name}_target_id, n.status, COALESCE(n.sticky, 0), n.created
    FROM {" . $table . "} f
    JOIN {node_field_data} n ON n.nid = f.entity_id AND n.status = 1
    JOIN {taxonomy_term_field_data} t ON t.tid = f.{$name}_target_id
    WHERE f.deleted = 0
  ");
}
$after = (int) $db->query('SELECT COUNT(*) FROM {taxonomy_index}')->fetchField();
printf("taxonomy_index rebuilt from %d fields: %d -> %d rows\n", count($fields), $before, $after);
