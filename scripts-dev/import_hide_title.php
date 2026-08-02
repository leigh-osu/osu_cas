<?php

/**
 * @file
 * Carry D7's per-node "hide title" boolean into exclude_node_title.
 *
 * D7 stored field_node_hide_title on page and paragraph_page nodes (both
 * migrate to the D10 page bundle); the degree-fact-sheet types carry their
 * boolean as a real migrated field and are not handled here. D10's
 * exclude_node_title module (configured content_types: page: user) keeps
 * its per-node list in STATE (exclude_node_title_nid_list), which no
 * migration yml can target — so this post-migration step reads the D7
 * values from the migrate connection, maps the nids through the page
 * migrations' lookup tables, and merges them into the state list.
 *
 * Idempotent (set-merge). Run late in the rebuild, after node migrations:
 *   ddev drush --uri=agsci.oregonstate.edu scr scripts-dev/import_hide_title.php
 */

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;

$d7 = Database::getConnection('default', 'migrate');
$d7_nids = $d7->query(
  "SELECT entity_id FROM {field_data_field_node_hide_title}
   WHERE field_node_hide_title_value = 1
     AND entity_type = 'node'
     AND bundle IN ('page', 'paragraph_page')"
)->fetchCol();

if (!$d7_nids) {
  print "No hidden-title nodes found in D7 — nothing to do.\n";
  return;
}

// Migrations that produce D10 page nodes from the two D7 bundles. Book
// pages share the D7 'page' rows, so cas_book_to_page can own some nids.
$migrations = [
  'cas_page_to_page',
  'cas_paragraph_page_to_page',
  'cas_book_to_page',
  'cas_feature_page_to_page',
];
$lookup = \Drupal::service('migrate.lookup');

$mapped = [];
$missing = 0;
foreach ($d7_nids as $d7_nid) {
  $found = NULL;
  foreach ($migrations as $mid) {
    try {
      $ids = $lookup->lookup($mid, [$d7_nid]);
    }
    catch (\Exception $e) {
      continue;
    }
    if ($ids) {
      $found = reset($ids)['nid'] ?? NULL;
      break;
    }
  }
  // The node migrations preserve nids; accept a same-nid page as a
  // fallback when no map row matched (e.g. high-water reruns).
  if (!$found && ($n = Node::load($d7_nid)) && $n->bundle() === 'page') {
    $found = $d7_nid;
  }
  if ($found) {
    $mapped[] = (int) $found;
  }
  else {
    $missing++;
  }
}

$state = \Drupal::state();
$list = $state->get('exclude_node_title_nid_list') ?: [];
$merged = array_values(array_unique(array_merge($list, $mapped)));
$state->set('exclude_node_title_nid_list', $merged);

printf(
  "hide-title: %d D7 nodes, %d mapped, %d unmatched; exclude list now %d nids.\n",
  count($d7_nids),
  count($mapped),
  $missing,
  count($merged)
);
