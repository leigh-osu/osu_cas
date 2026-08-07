<?php

/**
 * @file
 * One-off: normalize legacy D7 button markup to the CAS schemes.
 *
 * Applies CasLarchInlineClasses::normalizeButtons() (now part of the
 * migration transform chain, so rebuilds produce this directly) to rich
 * text already in the DB: orange D7 variants (btn-primary/btn-osu, per
 * osu_buttons.css) become btn cas-button-dark, every other variant
 * (btn-stratosphere, btn-moondust, color-active, inline color styles)
 * becomes btn cas-button-light; btn-large/small/mini map to btn-lg/btn-sm,
 * btn-block to w-100. Idempotent. If run against a DB already swept by the
 * pre-orange-rule version, follow with recover_orange_buttons.php.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/normalize_legacy_buttons.php
 */

use Drupal\Core\Database\Database;
use Drupal\osu_migrations_cas\Plugin\migrate\process\CasLarchInlineClasses;

$db = Database::getConnection();

$targets = [
  ['node__body', 'node_revision__body', 'body_value'],
  ['block_content__body', 'block_content_revision__body', 'body_value'],
  ['paragraph__field_p_accordion_body', 'paragraph_revision__field_p_accordion_body', 'field_p_accordion_body_value'],
];

$updated = 0;
foreach ($targets as [$table, $revision_table, $column]) {
  foreach ([$table, $revision_table] as $t) {
    $rows = $db->query("SELECT entity_id, revision_id, delta, langcode, $column AS v FROM $t WHERE $column LIKE :p", [':p' => '%btn%'])->fetchAll();
    foreach ($rows as $row) {
      $new = CasLarchInlineClasses::normalizeButtons($row->v);
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
print "Done: $updated rows normalized.\n";
