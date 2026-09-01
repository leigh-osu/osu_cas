<?php

/**
 * @file
 * Pre-seeds migrate_map_mmi_membership_terms with the name-matched terms.
 *
 * An MMI functional_groups term whose name (case-insensitive) already exists
 * in the live membership_types vocabulary IS that role: its map row points at
 * the existing tid so mmi_membership_terms skips it and every lookup resolves
 * to the live term. Rows are written ROLLBACK_PRESERVE so a rollback can
 * never delete a live term. Approved mapping (2026-08-28): 7 adoptions, 10
 * creations, 5 unreferenced terms skipped by the source plugin.
 *
 * Only terms actually referenced by a profile are seeded, mirroring the
 * mmi_d7_term_referenced source restriction. Idempotent. Run before
 * `drush mim mmi_membership_terms` -- mmi_migrate.sh section 4 sequences it.
 */

use Drupal\Core\Database\Database;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Row;

$mmi = Database::getConnection('default', 'migrate_mmi');
$d10 = Database::getConnection();

$live = [];
foreach ($d10->query("SELECT LOWER(t.name) n, t.tid FROM {taxonomy_term_field_data} t
  JOIN {taxonomy_term_data} td ON td.tid = t.tid WHERE td.vid = 'membership_types'") as $r) {
  $live[$r->n] = (int) $r->tid;
}

$migration = \Drupal::service('plugin.manager.migration')->createInstance('mmi_membership_terms');
$id_map = $migration->getIdMap();

$seeded = $kept = 0;
foreach ($mmi->query("SELECT t.tid, t.name FROM {taxonomy_term_data} t
  JOIN {taxonomy_vocabulary} v ON v.vid = t.vid AND v.machine_name = 'functional_groups'
  WHERE EXISTS (SELECT 1 FROM {field_data_functional_group} f
    WHERE f.functional_group_tid = t.tid AND f.deleted = 0)") as $r) {
  $name = strtolower(trim($r->name));
  if (!isset($live[$name])) {
    continue;
  }
  if ($id_map->lookupDestinationIds(['tid' => $r->tid])) {
    $kept++;
    continue;
  }
  $row = new Row(['tid' => $r->tid], ['tid' => ['type' => 'integer']]);
  $id_map->saveIdMapping($row, [$live[$name]], MigrateIdMapInterface::STATUS_IMPORTED, MigrateIdMapInterface::ROLLBACK_PRESERVE);
  printf("  adopt d7 term %d (%s) -> live tid %d\n", $r->tid, $r->name, $live[$name]);
  $seeded++;
}
printf("pre-seeded %d term adoption rows (%d already present)\n", $seeded, $kept);
