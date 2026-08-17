<?php

/**
 * @file
 * Backfill the enterprise budget PDF / XLS attachments.
 *
 * D7 stored these as link fields holding file URLs (/sites/agscid7/files/
 * oaeb/...). cas_enterprise_budgets_to_enterprise_budgets resolves each URL
 * to a document media through the cas_file_url_to_media process plugin, which
 * needs the physical file staged under public://oaeb/. Only part of that tree
 * had been copied, so URLs whose file was absent resolved to NULL and were
 * dropped: 123 of 147 PDFs and 49 of 55 spreadsheets survived.
 *
 * With the full oaeb/ tree in place this re-resolves every D7 URL and fills
 * the two fields. Idempotent: a node whose field already holds the same media
 * ids is left untouched, and the plugin finds existing File/Media entities
 * rather than duplicating them.
 *
 * Usage: drush scr scripts-dev/backfill_aeb_attachments.php
 */

use Drupal\Core\Database\Database;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

$d7 = Database::getConnection('default', 'migrate');
$db = \Drupal::database();
$map = $db->query('SELECT sourceid1, destid1 FROM {migrate_map_cas_enterprise_budgets_to_enterprise_budgets} WHERE destid1 IS NOT NULL')->fetchAllKeyed();

/** @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface $manager */
$manager = \Drupal::service('plugin.manager.migration');
$migration = $manager->createInstance('cas_enterprise_budgets_to_enterprise_budgets');
$plugin = \Drupal::service('plugin.manager.migrate.process')
  ->createInstance('cas_file_url_to_media', [], $migration);
$executable = new MigrateExecutable($migration);

// [D7 field table => [D7 url column, D10 field]].
$fields = [
  'field_data_field_pdf' => ['field_pdf_url', 'field_aeb_pdf'],
  'field_data_field_aeb_xls' => ['field_aeb_xls_url', 'field_aeb_xls'],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$totals = [];
foreach ($fields as $table => [$column, $d10_field]) {
  $by_node = [];
  $rows = $d7->query("SELECT entity_id, delta, $column AS url FROM {" . $table . "} WHERE entity_type = 'node' AND bundle = 'enterprise_budgets' AND deleted = 0 ORDER BY entity_id, delta");
  foreach ($rows as $r) {
    if (isset($map[$r->entity_id]) && $r->url !== NULL && $r->url !== '') {
      $by_node[$map[$r->entity_id]][] = $r->url;
    }
  }
  $filled = $unchanged = $unresolved = 0;
  foreach ($by_node as $nid => $urls) {
    $node = $storage->load($nid);
    if (!$node || !$node->hasField($d10_field)) {
      continue;
    }
    $mids = [];
    foreach ($urls as $url) {
      $mid = $plugin->transform($url, $executable, new Row(), 'target_id');
      if ($mid) {
        $mids[] = (int) $mid;
      }
      else {
        $unresolved++;
        print "  unresolved: $url (node $nid)\n";
      }
    }
    $current = array_map(fn($v) => (int) $v['target_id'], $node->get($d10_field)->getValue());
    if ($current === $mids) {
      $unchanged++;
      continue;
    }
    $node->set($d10_field, array_map(fn($m) => ['target_id' => $m], $mids));
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    $filled++;
  }
  $totals[$d10_field] = [$filled, $unchanged, $unresolved];
}
foreach ($totals as $field => [$filled, $unchanged, $unresolved]) {
  printf("%s: %d nodes updated, %d already correct, %d URLs unresolved\n", $field, $filled, $unchanged, $unresolved);
}
