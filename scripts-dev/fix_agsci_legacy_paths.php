<?php

/**
 * @file
 * One-off: rewrite /sites/agsci/files/ legacy URLs in migrated rich text.
 *
 * CasLegacyFilePaths originally only handled /sites/agscid7/files/ and
 * /sites/default/files/; D7 editors also hardcoded /sites/agsci/files/.
 * The plugin now covers that variant for future rebuilds; this fixes rows
 * already in the DB, using the plugin's own resolver (which verifies the
 * target file exists, copying it from the D7 files tree if needed).
 * Idempotent.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_agsci_legacy_paths.php
 */

use Drupal\Core\Database\Database;
use Drupal\osu_migrations_cas\Plugin\migrate\process\CasLegacyFilePaths;

$db = Database::getConnection();

$targets = [
  ['node__body', 'node_revision__body', 'body_value'],
  ['block_content__body', 'block_content_revision__body', 'body_value'],
  ['paragraph__field_p_accordion_body', 'paragraph_revision__field_p_accordion_body', 'field_p_accordion_body_value'],
];

$updated = 0;
foreach ($targets as [$table, $revision_table, $column]) {
  foreach ([$table, $revision_table] as $t) {
    $rows = $db->query("SELECT entity_id, revision_id, delta, langcode, $column AS v FROM $t WHERE $column LIKE :p", [':p' => '%/sites/agsci/files/%'])->fetchAll();
    foreach ($rows as $row) {
      $new = CasLegacyFilePaths::rewriteText($row->v);
      if ($new !== $row->v) {
        $db->update($t)
          ->fields([$column => $new])
          ->condition('entity_id', $row->entity_id)
          ->condition('revision_id', $row->revision_id)
          ->condition('delta', $row->delta)
          ->condition('langcode', $row->langcode)
          ->execute();
        $updated++;
      }
    }
    print "$t: " . count($rows) . " candidate rows\n";
  }
}
print "Done: $updated rows rewritten.\n";
$remaining = $db->query("SELECT COUNT(*) FROM node__body WHERE body_value LIKE :p", [':p' => '%/sites/agsci/files/%'])->fetchField();
print "node__body rows still containing the pattern (unresolvable files): $remaining\n";
