<?php

/**
 * @file
 * Backfill field_tax_department on pages migrated from D7 feature_page.
 *
 * cas_feature_page_to_page now maps D7 field_department -> field_tax_department
 * (unit terms; the Faces of AgSci Department filter and the ?tid_1=<unit>
 * links depend on it). This fills the field on a database migrated before
 * that mapping existed. Idempotent: pages that already carry the field are
 * left alone. Term ids are the same on both sides.
 *
 * Usage: drush scr scripts-dev/backfill_page_department.php
 */

use Drupal\Core\Database\Database;

$d7 = Database::getConnection('default', 'migrate');
$db = \Drupal::database();
$map = $db->query('SELECT sourceid1, destid1 FROM {migrate_map_cas_feature_page_to_page} WHERE destid1 IS NOT NULL')->fetchAllKeyed();
$rows = $d7->query("SELECT entity_id, delta, field_department_tid AS tid FROM {field_data_field_department} WHERE entity_type = 'node' AND bundle = 'feature_page' ORDER BY entity_id, delta");
$by_node = [];
foreach ($rows as $r) {
  if (isset($map[$r->entity_id])) {
    $by_node[$map[$r->entity_id]][] = (int) $r->tid;
  }
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$updated = $skipped = $missing = 0;
foreach ($by_node as $nid => $tids) {
  $node = $storage->load($nid);
  if (!$node || !$node->hasField('field_tax_department')) {
    $missing++;
    continue;
  }
  if (!$node->get('field_tax_department')->isEmpty()) {
    $skipped++;
    continue;
  }
  $tids = array_values(array_unique(array_filter($tids, fn($t) => $terms->load($t))));
  if (!$tids) {
    $missing++;
    continue;
  }
  $node->set('field_tax_department', array_map(fn($t) => ['target_id' => $t], $tids));
  $node->setNewRevision(FALSE);
  $node->setSyncing(TRUE);
  $node->save();
  $updated++;
}
printf("Department backfilled: %d  already set: %d  no node/term: %d\n", $updated, $skipped, $missing);
