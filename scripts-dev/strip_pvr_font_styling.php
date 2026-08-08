<?php

/**
 * @file
 * One-off: strip typeface/size coding from plant variety release text.
 *
 * Applies CasStripFontStyling::stripText() (now in the cas_pvr_to_pvr
 * migration pipelines, so rebuilds produce this directly) to PVR rich
 * text already in the DB: font-family / font-size style declarations and
 * <font face/size> tags are removed; bold, italic and all other styling
 * survive. Current and revision tables. Idempotent.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/strip_pvr_font_styling.php
 */

use Drupal\Core\Database\Database;
use Drupal\osu_migrations_cas\Plugin\migrate\process\CasStripFontStyling;

$db = Database::getConnection();

// Every text field on the bundle, plus body.
$fields = ['body'];
foreach (\Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['entity_type' => 'node', 'bundle' => 'plant_variety_release']) as $fc) {
  if (in_array($fc->getType(), ['text_long', 'text_with_summary', 'text'], TRUE)) {
    $fields[] = $fc->getName();
  }
}

$updated = 0;
foreach (array_unique($fields) as $field) {
  $column = ($field === 'body' ? 'body' : $field) . '_value';
  foreach (["node__$field", "node_revision__$field"] as $table) {
    if (!$db->schema()->tableExists($table)) {
      continue;
    }
    $rows = $db->query(
      "SELECT entity_id, revision_id, delta, langcode, $column AS v FROM {$table}
       WHERE bundle = 'plant_variety_release'
         AND ($column LIKE '%font-family%' OR $column LIKE '%font-size%' OR $column LIKE '%<font%')"
    )->fetchAll();
    foreach ($rows as $row) {
      $new = CasStripFontStyling::stripText($row->v);
      if ($new !== $row->v) {
        $db->update($table)
          ->fields([$column => $new])
          ->condition('entity_id', $row->entity_id)
          ->condition('revision_id', $row->revision_id)
          ->condition('delta', $row->delta)
          ->condition('langcode', $row->langcode)
          ->execute();
        $updated++;
      }
    }
    if ($rows) {
      print "$table: " . count($rows) . " candidate rows\n";
    }
  }
}
print "Done: $updated rows cleaned.\n";
