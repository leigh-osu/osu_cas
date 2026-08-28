<?php

/**
 * @file
 * Pre-seeds the mmi biblio term maps with name-matched live terms.
 *
 * The publication_authors and publication_keywords vocabularies are SHARED
 * with the agsci publications. An MMI contributor or keyword whose name
 * already exists there (case-insensitive) IS that term: its map row points at
 * the existing tid so the mmi_biblio_authors / mmi_biblio_keywords imports
 * skip it and every mmi_biblio lookup resolves to the live term — a person's
 * author-term page keeps collecting everything they touched across both
 * sites. Rows are written ROLLBACK_PRESERVE so a rollback can never delete a
 * live term. Survey 2026-08-28: 292 of 2,700 contributors and 7 of 47
 * keywords match.
 *
 * The source queries mirror the cas_biblio_authors / cas_biblio_keywords
 * source plugins row for row. Idempotent. Run before
 * `drush mim mmi_biblio_authors mmi_biblio_keywords` — mmi_migrate.sh
 * section 6 sequences it.
 */

use Drupal\Core\Database\Database;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Row;

$mmi = Database::getConnection('default', 'migrate_mmi');
$d10 = Database::getConnection();

$live_terms = static function (string $vocab) use ($d10): array {
  $live = [];
  // Lowest tid wins where the live vocab holds case-duplicate names.
  foreach ($d10->query("SELECT LOWER(t.name) n, t.tid FROM {taxonomy_term_field_data} t
    JOIN {taxonomy_term_data} td ON td.tid = t.tid WHERE td.vid = :v
    ORDER BY t.tid DESC", [':v' => $vocab]) as $r) {
    $live[$r->n] = (int) $r->tid;
  }
  return $live;
};

$seed = static function (string $migration_id, array $source, array $live, string $id_key) {
  $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
  $id_map = $migration->getIdMap();
  $seeded = $kept = 0;
  foreach ($source as $id => $name) {
    $key = strtolower(trim($name));
    if ($key === '' || !isset($live[$key])) {
      continue;
    }
    if ($id_map->lookupDestinationIds([$id_key => $id])) {
      $kept++;
      continue;
    }
    $row = new Row([$id_key => $id], [$id_key => ['type' => 'integer']]);
    $id_map->saveIdMapping($row, [$live[$key]], MigrateIdMapInterface::STATUS_IMPORTED, MigrateIdMapInterface::ROLLBACK_PRESERVE);
    $seeded++;
  }
  printf("%s: pre-seeded %d adoption rows (%d already present)\n", $migration_id, $seeded, $kept);
};

// Contributors actually credited on an entry (= cas_biblio_authors query).
$authors = $mmi->query("SELECT DISTINCT bcd.cid, bcd.name FROM {biblio_contributor_data} bcd
  INNER JOIN {biblio_contributor} bc ON bc.cid = bcd.cid")->fetchAllKeyed();
$seed('mmi_biblio_authors', $authors, $live_terms('publication_authors'), 'cid');

// Every keyword in the dictionary (= cas_biblio_keywords query).
$keywords = $mmi->query('SELECT bkd.kid, bkd.word FROM {biblio_keyword_data} bkd')->fetchAllKeyed();
$seed('mmi_biblio_keywords', $keywords, $live_terms('publication_keywords'), 'kid');
