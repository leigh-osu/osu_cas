<?php

/**
 * @file
 * Pre-seeds migrate_map_mmi_users with the ONID-adopted accounts.
 *
 * An MMI D7 user whose cas_user.cas_name matches a live D10 CAS authmap entry
 * IS that person: their map row points at the existing uid so mmi_users skips
 * them (already imported) and every migration_lookup resolves authorship to
 * the live account. Rows are written ROLLBACK_PRESERVE so a rollback of
 * mmi_users can never delete a live agsci account.
 *
 * Only accounts actually referenced by MMI content/og/webforms are seeded,
 * mirroring the mmi_d7_user_referenced source restriction. Idempotent: rows
 * that already exist are left alone. Run before `drush mim mmi_users` --
 * scripts-dev/mmi_migrate.sh section 4 sequences this.
 */

use Drupal\Core\Database\Database;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Row;

$mmi = Database::getConnection('default', 'migrate_mmi');
$d10 = Database::getConnection();

$referenced = array_flip($mmi->query("
  SELECT DISTINCT uid FROM {node} WHERE uid > 0
  UNION SELECT uid FROM {node_revision} WHERE uid > 0
  UNION SELECT etid FROM {og_membership} WHERE entity_type = 'user' AND etid > 0
  UNION SELECT uid FROM {webform_submissions} WHERE uid > 0")->fetchCol());

$authmap = [];
foreach ($d10->query("SELECT LOWER(authname) an, uid FROM {authmap} WHERE provider = 'cas'") as $r) {
  $authmap[$r->an] = (int) $r->uid;
}

$migration = \Drupal::service('plugin.manager.migration')->createInstance('mmi_users');
$id_map = $migration->getIdMap();

$seeded = $kept = 0;
foreach ($mmi->query("SELECT c.uid, c.cas_name FROM {cas_user} c JOIN {users} u ON u.uid = c.uid WHERE c.uid > 0") as $r) {
  $onid = strtolower(trim($r->cas_name));
  if (!isset($referenced[$r->uid]) || !isset($authmap[$onid])) {
    continue;
  }
  if ($id_map->lookupDestinationIds(['uid' => $r->uid])) {
    $kept++;
    continue;
  }
  $row = new Row(['uid' => $r->uid], ['uid' => ['type' => 'integer']]);
  $id_map->saveIdMapping($row, [$authmap[$onid]], MigrateIdMapInterface::STATUS_IMPORTED, MigrateIdMapInterface::ROLLBACK_PRESERVE);
  printf("  adopt d7 uid %d (%s) -> live uid %d\n", $r->uid, $r->cas_name, $authmap[$onid]);
  $seeded++;
}
printf("pre-seeded %d adoption rows (%d already present)\n", $seeded, $kept);
