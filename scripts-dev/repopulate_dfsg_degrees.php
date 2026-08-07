<?php

/**
 * @file
 * One-off: copy D7 field_dfsg_degrees collection items onto the merged
 * degree_fact_sheet nodes (field_dfs_degree_title / _level_text /
 * _description), for DBs migrated before cas_dfsg_to_dfsg gained the
 * cas_dfsg_degrees mappings. High-water blocks a targeted re-import, so
 * this writes the fields directly. Safe to re-run (values are replaced).
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/repopulate_dfsg_degrees.php
 */

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;

$d7 = Database::getConnection('default', 'migrate');
$d10 = Database::getConnection();

// D7 nid -> ordered collection items.
$items = $d7->query("
  SELECT d.entity_id nid, d.delta,
    t.field_degree_title_value title,
    l.field_dfsg_degree_level_value lvl,
    dd.field_dfsg_degree_description_value descr
  FROM field_data_field_dfsg_degrees d
  LEFT JOIN field_data_field_degree_title t
    ON t.entity_id = d.field_dfsg_degrees_value AND t.entity_type = 'field_collection_item'
  LEFT JOIN field_data_field_dfsg_degree_level l
    ON l.entity_id = d.field_dfsg_degrees_value AND l.entity_type = 'field_collection_item'
  LEFT JOIN field_data_field_dfsg_degree_description dd
    ON dd.entity_id = d.field_dfsg_degrees_value AND dd.entity_type = 'field_collection_item'
  WHERE d.entity_type = 'node'
  ORDER BY d.entity_id, d.delta")->fetchAll();

$by_d7_nid = [];
foreach ($items as $r) {
  $by_d7_nid[$r->nid][] = $r;
}

$map = $d10->query("SELECT sourceid1, destid1 FROM migrate_map_cas_dfsg_to_dfsg WHERE destid1 IS NOT NULL")->fetchAllKeyed();

$updated = 0;
foreach ($by_d7_nid as $d7_nid => $rows) {
  if (empty($map[$d7_nid])) {
    print "SKIP d7 nid $d7_nid: no migrated node\n";
    continue;
  }
  $node = Node::load($map[$d7_nid]);
  if (!$node) {
    print "SKIP d7 nid $d7_nid: node {$map[$d7_nid]} missing\n";
    continue;
  }
  $titles = $levels = $descs = [];
  foreach ($rows as $r) {
    $titles[] = trim((string) $r->title);
    $levels[] = trim((string) $r->lvl);
    $descr = trim((string) $r->descr);
    // A single space keeps an empty slot so deltas stay aligned.
    $descs[] = ['value' => $descr !== '' ? $descr : ' ', 'format' => 'full_html'];
  }
  $node->set('field_dfs_degree_title', $titles);
  $node->set('field_dfs_degree_level_text', $levels);
  $node->set('field_dfs_degree_description', $descs);
  $node->save();
  $updated++;
  print "OK d7 $d7_nid -> d10 {$node->id()} ({$node->label()}): " . count($rows) . " degrees\n";
}
print "Done: $updated nodes updated.\n";
