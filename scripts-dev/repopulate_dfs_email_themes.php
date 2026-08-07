<?php

/**
 * @file
 * One-off: backfill field_dfs_advising_email and field_tax_cas_themes on
 * undergrad degree fact sheets migrated before cas_dfs_to_dfs was fixed
 * (the email mapping dropped D7's 'email' column, and themes were written
 * to a nonexistent field name). High-water blocks re-import, so write the
 * fields directly. Safe to re-run.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/repopulate_dfs_email_themes.php
 */

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;

$d7 = Database::getConnection('default', 'migrate');
$d10 = Database::getConnection();

$map = $d10->query("SELECT sourceid1, destid1 FROM migrate_map_cas_dfs_to_dfs WHERE destid1 IS NOT NULL")->fetchAllKeyed();
$term_map = $d10->query("SELECT sourceid1, destid1 FROM migrate_map_upgrade_d7_taxonomy_terms__cas_impact_areas WHERE destid1 IS NOT NULL")->fetchAllKeyed();

$emails = $d7->query("SELECT entity_id, field_dfs_advising_email_email FROM field_data_field_dfs_advising_email WHERE entity_type='node' AND bundle='degree_fact_sheet'")->fetchAllKeyed();

$themes = [];
foreach ($d7->query("SELECT entity_id, field_cas_themes_tid FROM field_data_field_cas_themes WHERE entity_type='node' AND bundle='degree_fact_sheet' ORDER BY entity_id, delta") as $r) {
  $themes[$r->entity_id][] = $r->field_cas_themes_tid;
}

$updated = 0;
foreach ($map as $d7_nid => $d10_nid) {
  $email = $emails[$d7_nid] ?? NULL;
  $tids = [];
  foreach ($themes[$d7_nid] ?? [] as $tid) {
    if (!empty($term_map[$tid])) {
      $tids[] = $term_map[$tid];
    }
    else {
      print "WARN d7 $d7_nid: term $tid not in cas_impact_areas map\n";
    }
  }
  if ($email === NULL && !$tids) {
    continue;
  }
  $node = Node::load($d10_nid);
  if (!$node) {
    print "SKIP d7 $d7_nid: node $d10_nid missing\n";
    continue;
  }
  if ($email !== NULL) {
    $node->set('field_dfs_advising_email', $email);
  }
  if ($tids) {
    $node->set('field_tax_cas_themes', $tids);
  }
  $node->save();
  $updated++;
  print "OK d10 $d10_nid ({$node->label()}): " . ($email ? "email=$email " : '') . ($tids ? 'themes=' . implode(',', $tids) : '') . "\n";
}
print "Done: $updated nodes updated.\n";
