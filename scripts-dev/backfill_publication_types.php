<?php

/**
 * @file
 * One-off: backfill field_pub_type from D7 biblio types.
 *
 * upgrade_d7_biblio_publication resolves field_pub_type with entity_lookup
 * by term name, but the publication_type vocabulary only carried six
 * OSU-standard terms, so every publication whose D7 type name was not
 * among them (Journal Article, Report, Conference Paper, ...) migrated
 * typeless (~7,900 of 8,171). The migration now uses entity_generate;
 * this repairs the current DB: create the missing terms with the D7
 * names, then fill the field (current + default revision) for every
 * publication that is missing it, keyed by nid (biblio nids are
 * preserved). Idempotent.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/backfill_publication_types.php
 */

use Drupal\Core\Database\Database;
use Drupal\taxonomy\Entity\Term;

$db = Database::getConnection();
$mig = Database::getConnection('default', 'migrate');

// D7 type name per publication nid.
$d7_types = $mig->query(
  "SELECT b.nid, bt.name FROM biblio b
   JOIN biblio_types bt ON bt.tid = b.biblio_type"
)->fetchAllKeyed();

// Existing D10 terms by name; create the missing ones.
$terms = $db->query("SELECT LOWER(name), tid FROM taxonomy_term_field_data WHERE vid = 'publication_type'")->fetchAllKeyed();
foreach (array_unique($d7_types) as $name) {
  if (!isset($terms[strtolower($name)])) {
    $term = Term::create(['vid' => 'publication_type', 'name' => $name]);
    $term->save();
    $terms[strtolower($name)] = $term->id();
    print "created term: $name ({$term->id()})\n";
  }
}

// Publications missing a type, with their current vid.
$rows = $db->query(
  "SELECT n.nid, n.vid FROM node_field_data n
   WHERE n.type = 'publications'
     AND NOT EXISTS (SELECT 1 FROM node__field_pub_type t WHERE t.entity_id = n.nid)"
)->fetchAllKeyed();

$filled = 0;
$no_source = 0;
foreach ($rows as $nid => $vid) {
  $name = $d7_types[$nid] ?? NULL;
  if ($name === NULL || !isset($terms[strtolower($name)])) {
    $no_source++;
    continue;
  }
  $fields = [
    'bundle' => 'publications',
    'deleted' => 0,
    'entity_id' => $nid,
    'revision_id' => $vid,
    'langcode' => 'en',
    'delta' => 0,
    'field_pub_type_target_id' => $terms[strtolower($name)],
  ];
  $db->insert('node__field_pub_type')->fields($fields)->execute();
  $db->insert('node_revision__field_pub_type')->fields($fields)->execute();
  $filled++;
}
print "Done: $filled publications filled, $no_source without a D7 type.\n";
