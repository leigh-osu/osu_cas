<?php

/**
 * @file
 * One-off: move slot-2 "Author"-typed contributors from authors to editors.
 *
 * biblio renders contributors by SLOT: auth_category 2 shows as
 * "Secondary Authors" regardless of the auth_type, which editors often
 * left at its Author default. The CAS contributor split keyed on
 * auth_type only, so 81 slot-2 assignments on 17 publications read as
 * co-authors in D10. CasBiblioReferenceDomain now includes slot 2; this
 * repairs a DB migrated before that: for each affected publication, the
 * matching Publication Authors terms move from field_pub_authors to
 * field_pub_editors (appended in D7 rank order), current + default
 * revision. Idempotent.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_publication_slot2_editors.php
 */

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;

$db = Database::getConnection();
$mig = Database::getConnection('default', 'migrate');

// D7: slot-2 contributors NOT already captured by the auth_type rule,
// grouped per publication in rank order.
$rows = $mig->query(
  "SELECT bc.nid, bc.cid, bcd.name
   FROM biblio_contributor bc
   JOIN biblio_contributor_data bcd ON bcd.cid = bc.cid
   WHERE bc.auth_category = 2 AND bc.auth_type NOT IN (2, 10, 14)
   ORDER BY bc.nid, bc.rank"
)->fetchAll();
$by_pub = [];
foreach ($rows as $r) {
  $by_pub[$r->nid][] = $r;
}
print count($rows) . " slot-2 assignments on " . count($by_pub) . " publications\n";

$moved = 0;
foreach ($by_pub as $nid => $contributors) {
  $node = Node::load($nid);
  if (!$node || $node->bundle() !== 'publications') {
    print "nid $nid: no publication node, skipped\n";
    continue;
  }
  $changed = FALSE;
  foreach ($contributors as $c) {
    // cid -> Publication Authors term via the migrate map.
    $tid = $db->query("SELECT destid1 FROM migrate_map_cas_biblio_authors WHERE sourceid1 = :c", [':c' => $c->cid])->fetchField();
    if (!$tid) {
      print "nid $nid: no term for cid {$c->cid} ({$c->name}), skipped\n";
      continue;
    }
    // Already an editor? (idempotency)
    $editor_tids = array_column($node->get('field_pub_editors')->getValue(), 'target_id');
    if (in_array($tid, $editor_tids)) {
      continue;
    }
    // Remove from authors (first matching delta only — a person can
    // legitimately appear once as author-slot too, but slot data says
    // this occurrence belongs to slot 2).
    $authors = $node->get('field_pub_authors')->getValue();
    foreach ($authors as $delta => $item) {
      if ((int) $item['target_id'] === (int) $tid) {
        unset($authors[$delta]);
        break;
      }
    }
    $node->set('field_pub_authors', array_values($authors));
    $node->get('field_pub_editors')->appendItem(['target_id' => $tid]);
    $changed = TRUE;
    $moved++;
  }
  if ($changed) {
    $node->save();
  }
}
print "Done: $moved assignments moved to editors.\n";
