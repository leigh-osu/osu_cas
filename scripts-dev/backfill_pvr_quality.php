<?php

/**
 * @file
 * One-off: backfill field_pvr_quality from D7's field_plant_patent.
 *
 * D7's field_plant_patent carries the instance label "Quality" and renders
 * on every plant variety release; the cas_pvr_to_pvr migration (now fixed)
 * never mapped it, so the D10 Quality field sat empty. Copies the D7
 * values onto the D10 nodes (current + default revision), running the
 * same rich-text repairs the pipeline applies. Idempotent: rows already
 * present are skipped.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/backfill_pvr_quality.php
 */

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\osu_migrations_cas\Plugin\migrate\process\CasLarchInlineClasses;
use Drupal\osu_migrations_cas\Plugin\migrate\process\CasStripFontStyling;

$mig = Database::getConnection('default', 'migrate');

$rows = $mig->query(
  "SELECT entity_id, delta, field_plant_patent_value v
   FROM field_data_field_plant_patent
   WHERE entity_type = 'node' AND bundle = 'plant_variety_release'
   ORDER BY entity_id, delta"
)->fetchAll();

$filled = 0;
$skipped = 0;
foreach ($rows as $row) {
  $node = Node::load($row->entity_id);
  if (!$node || $node->bundle() !== 'plant_variety_release') {
    print "nid {$row->entity_id}: no matching D10 node, skipped\n";
    continue;
  }
  if (!$node->get('field_pvr_quality')->isEmpty()) {
    $skipped++;
    continue;
  }
  if (str_contains($row->v, '[[{')) {
    print "nid {$row->entity_id}: has a D7 media token, needs the full pipeline -- skipped\n";
    continue;
  }
  $text = CasStripFontStyling::stripText(CasLarchInlineClasses::mapText($row->v));
  $node->get('field_pvr_quality')->appendItem(['value' => $text, 'format' => 'full_html']);
  $node->save();
  $filled++;
}
print "Done: $filled nodes filled, $skipped already had values.\n";
