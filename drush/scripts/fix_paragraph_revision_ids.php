<?php

/**
 * @file
 * Repairs stale paragraphs_item.revision_id pointers in the D7 migrate DB.
 *
 * D7 occasionally leaves paragraphs_item.revision_id pointing at an older
 * revision than the one the host entity's *current* field_data_<field>
 * row references. The contrib d7_paragraphs_item source plugin joins and
 * resolves the host parent using that stale revision_id, so prepareRow()
 * finds no parent, returns FALSE, and the paragraph is silently dropped
 * from every paragraph_*__to__layout_builder migration. Downstream node
 * migrations (cas_page_to_page, cas_paragraph_page_to_page,
 * cas_feature_page_to_page) then log "Unable to find related migrated
 * block for source id N" and the page loses that content.
 *
 * This script re-points paragraphs_item.revision_id to the revision the
 * live host currently references. It only ever moves the pointer to a
 * revision that actually exists in paragraphs_item_revision, only touches
 * non-archived rows that are genuinely stale, and is fully idempotent —
 * safe to re-run after every fresh import of the prod D7 DB.
 *
 * Usage (against the DDEV migrate DB):
 *   ddev drush scr drush/scripts/fix_paragraph_revision_ids.php
 *   ddev drush scr drush/scripts/fix_paragraph_revision_ids.php -- dry-run
 *
 * It operates ONLY on the 'migrate' database connection (the local D7
 * source copy), never on the Drupal 10 site DB.
 */

use Drupal\Core\Database\Database;

$dry_run = in_array('dry-run', $extra ?? [], TRUE);

$db = Database::getConnection('default', 'migrate');
$schema = $db->schema();

if (!$schema->tableExists('paragraphs_item') || !$schema->tableExists('paragraphs_item_revision')) {
  echo "ERROR: 'migrate' connection has no paragraphs_item / paragraphs_item_revision tables.\n";
  echo "Confirm \$databases['migrate'] points at the D7 source DB before running.\n";
  return;
}

echo $dry_run
  ? "=== DRY RUN — no rows will be written ===\n"
  : "=== APPLYING fix to the 'migrate' (D7 source) database ===\n";

// Each paragraph is held by exactly one host field; that field's name is
// stored on the paragraph row. Repair one field table at a time so the
// dynamic table/column names stay correct.
$field_names = $db->query("
  SELECT DISTINCT field_name
  FROM {paragraphs_item}
  WHERE archived = 0 AND field_name IS NOT NULL AND field_name <> ''
")->fetchCol();

$grand_total = 0;

foreach ($field_names as $field_name) {
  $table = 'field_data_' . $field_name;
  $col_value = $field_name . '_value';
  $col_rev = $field_name . '_revision_id';

  if (!$schema->tableExists($table)) {
    echo sprintf("  skip  %-32s (no %s table)\n", $field_name, $table);
    continue;
  }
  if (!$schema->fieldExists($table, $col_value) || !$schema->fieldExists($table, $col_rev)) {
    echo sprintf("  skip  %-32s (missing %s/%s columns)\n", $field_name, $col_value, $col_rev);
    continue;
  }

  // The revision the live host currently references for each paragraph.
  // MAX() defensively collapses the rare case of a paragraph referenced
  // by more than one host row to the newest revision. The EXISTS guard
  // guarantees we never point at a revision missing from
  // paragraphs_item_revision (which the source plugin's inner join would
  // drop anyway — repointing there would corrupt, not fix).
  $select = "
    SELECT p.item_id, p.revision_id AS old_rev, host.host_rev AS new_rev
    FROM {paragraphs_item} p
    INNER JOIN (
      SELECT `$col_value` AS item_id, MAX(`$col_rev`) AS host_rev
      FROM {" . $table . "}
      WHERE `$col_value` IS NOT NULL AND `$col_rev` IS NOT NULL
      GROUP BY `$col_value`
    ) host ON host.item_id = p.item_id
    WHERE p.field_name = :fn
      AND p.archived = 0
      AND p.revision_id <> host.host_rev
      AND EXISTS (
        SELECT 1 FROM {paragraphs_item_revision} pr
        WHERE pr.item_id = p.item_id AND pr.revision_id = host.host_rev
      )
  ";

  $stale = $db->query($select, [':fn' => $field_name])->fetchAll();
  $count = count($stale);
  $grand_total += $count;

  if ($count === 0) {
    echo sprintf("  ok    %-32s 0 stale\n", $field_name);
    continue;
  }

  if ($dry_run) {
    echo sprintf("  WOULD %-32s %d stale (e.g. item %d: %d -> %d)\n",
      $field_name, $count, $stale[0]->item_id, $stale[0]->old_rev, $stale[0]->new_rev);
    continue;
  }

  $txn = $db->startTransaction();
  try {
    foreach ($stale as $r) {
      $db->update('paragraphs_item')
        ->fields(['revision_id' => $r->new_rev])
        ->condition('item_id', $r->item_id)
        ->execute();
    }
    unset($txn);
    echo sprintf("  FIXED %-32s %d rows re-pointed\n", $field_name, $count);
  }
  catch (\Exception $e) {
    $txn->rollBack();
    echo sprintf("  ERROR %-32s rolled back: %s\n", $field_name, $e->getMessage());
  }
}

echo $dry_run
  ? sprintf("=== DRY RUN complete: %d paragraph rows would be repaired ===\n", $grand_total)
  : sprintf("=== Done: %d paragraph rows repaired ===\n", $grand_total);
