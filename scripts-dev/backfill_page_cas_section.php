<?php

/**
 * @file
 * CAS Section terms onto pages consolidated from D7 feature pages.
 *
 * faces_of_agsci filters on the Student/Faculty/Alumni terms that D7 kept in
 * field_cas_section; the feature_page -> Basic page consolidation dropped
 * them. Copies the terms from the D7 database onto field_tax_cas_section of
 * the same-nid pages (term ids are preserved). One-time repair; rebuilds map
 * the field in cas_feature_page_to_page.
 *
 * Usage: drush scr scripts-dev/backfill_page_cas_section.php
 */

$migrate = \Drupal\Core\Database\Database::getConnection('default', 'migrate');
$rows = $migrate->query("
  SELECT f.entity_id AS nid, f.field_cas_section_tid AS tid
  FROM {field_data_field_cas_section} f
  JOIN {node} n ON n.nid = f.entity_id
  WHERE f.entity_type = 'node' AND n.type = 'feature_page'
  ORDER BY f.entity_id, f.delta
")->fetchAll();
$by_node = [];
foreach ($rows as $row) {
  $by_node[$row->nid][] = (int) $row->tid;
}
$terms = array_flip(array_map('intval', \Drupal::database()->query("SELECT tid FROM {taxonomy_term_field_data}")->fetchCol()));
$storage = \Drupal::entityTypeManager()->getStorage('node');
$set = $same = $missing = 0;
foreach ($storage->loadMultiple(array_keys($by_node)) as $node) {
  if ($node->bundle() !== 'page' || !$node->hasField('field_tax_cas_section')) {
    $missing++;
    continue;
  }
  $tids = array_values(array_filter($by_node[$node->id()], fn($t) => isset($terms[$t])));
  $current = array_map('intval', array_column($node->get('field_tax_cas_section')->getValue(), 'target_id'));
  if ($current === $tids) {
    $same++;
    continue;
  }
  $node->set('field_tax_cas_section', $tids);
  $node->setNewRevision(FALSE);
  $node->setSyncing(TRUE);
  $node->save();
  $set++;
}
$missing += count($by_node) - $set - $same - $missing;
printf("Set: %d  Already set: %d  Not a page/no field: %d\n", $set, $same, count($by_node) - $set - $same);
