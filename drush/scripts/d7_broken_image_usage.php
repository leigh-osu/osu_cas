<?php
/**
 * @file
 * Given a CSV of broken D10 media images (from find_broken_images.php),
 * look up where each underlying file is used in the LEGACY D7 site.
 *
 * Walks field_collection_item / paragraphs_item hosts back to their root node
 * and prints node id, title, type, and the host chain.
 *
 * @code
 * drush scr drush/scripts/d7_broken_image_usage.php -- /tmp/broken_images.csv /tmp/d7_usage.csv
 * @endcode
 */

$in_path  = $extra[0] ?? '/tmp/broken_images.csv';
$out_path = $extra[1] ?? NULL;

if (!file_exists($in_path)) {
  echo "Input CSV not found: {$in_path}\n";
  return;
}

$d10 = \Drupal\Core\Database\Database::getConnection('default');
$d7  = \Drupal\Core\Database\Database::getConnection('default', 'migrate');

// Load broken D10 fids from CSV.
$d10_fids = [];
$fh_in = fopen($in_path, 'r');
fgetcsv($fh_in);
while (($row = fgetcsv($fh_in)) !== FALSE) {
  $d10_fids[] = (int) $row[1];
}
fclose($fh_in);
echo "Loaded " . count($d10_fids) . " D10 fids.\n";

// Map D10 fid -> D7 fid.
$map = $d10->select('migrate_map_upgrade_d7_files', 'm')
  ->fields('m', ['sourceid1', 'destid1'])
  ->condition('destid1', $d10_fids, 'IN')
  ->execute()->fetchAllKeyed(1, 0); // destid1 => sourceid1
$d7_fids = array_values(array_map('intval', $map));
echo "Resolved " . count($d7_fids) . " D7 fids via migrate map.\n";

// Pull all file_usage rows for those D7 fids.
$usage = $d7->select('file_usage', 'u')
  ->fields('u', ['fid', 'module', 'type', 'id', 'count'])
  ->condition('u.fid', $d7_fids, 'IN')
  ->execute()->fetchAll();
echo "Found " . count($usage) . " file_usage rows in D7.\n\n";

// Helper: resolve a host entity chain to its root node.
$resolve = function (string $type, int $id, array $seen = []) use (&$resolve, $d7): array {
  $key = "{$type}:{$id}";
  if (isset($seen[$key])) return [];
  $seen[$key] = TRUE;

  if ($type === 'node') {
    $row = $d7->select('node', 'n')
      ->fields('n', ['nid', 'title', 'type'])
      ->condition('nid', $id)
      ->execute()->fetchAssoc();
    return $row ? [['nid' => (int) $row['nid'], 'title' => $row['title'], 'bundle' => $row['type'], 'chain' => "node:{$id}"]] : [['nid' => 0, 'title' => '(missing node ' . $id . ')', 'bundle' => '', 'chain' => "node:{$id}"]];
  }

  if ($type === 'field_collection_item') {
    $fci = $d7->select('field_collection_item', 'f')
      ->fields('f', ['item_id', 'field_name'])
      ->condition('item_id', $id)
      ->execute()->fetchAssoc();
    if (!$fci) return [['nid' => 0, 'title' => '(deleted fci ' . $id . ')', 'bundle' => '', 'chain' => "fci:{$id}"]];
    $tbl = 'field_data_' . $fci['field_name'];
    if (!$d7->schema()->tableExists($tbl)) return [['nid' => 0, 'title' => '(no field table for ' . $fci['field_name'] . ')', 'bundle' => '', 'chain' => "fci:{$id}"]];
    $col = $fci['field_name'] . '_value';
    $rows = $d7->select($tbl, 't')
      ->fields('t', ['entity_type', 'entity_id'])
      ->condition($col, $id)
      ->execute()->fetchAll();
    $out = [];
    foreach ($rows as $r) {
      foreach ($resolve($r->entity_type, (int) $r->entity_id, $seen) as $hit) {
        $hit['chain'] = "fci:{$id}({$fci['field_name']}) -> " . $hit['chain'];
        $out[] = $hit;
      }
    }
    return $out ?: [['nid' => 0, 'title' => '(orphan fci ' . $id . ')', 'bundle' => '', 'chain' => "fci:{$id}"]];
  }

  if ($type === 'paragraphs_item') {
    $pi = $d7->select('paragraphs_item', 'p')
      ->fields('p', ['item_id', 'field_name', 'bundle'])
      ->condition('item_id', $id)
      ->execute()->fetchAssoc();
    if (!$pi) return [['nid' => 0, 'title' => '(deleted paragraphs_item ' . $id . ')', 'bundle' => '', 'chain' => "para:{$id}"]];
    $tbl = 'field_data_' . $pi['field_name'];
    if (!$d7->schema()->tableExists($tbl)) return [['nid' => 0, 'title' => '(no field table for ' . $pi['field_name'] . ')', 'bundle' => '', 'chain' => "para:{$id}"]];
    $col = $pi['field_name'] . '_value';
    $rows = $d7->select($tbl, 't')
      ->fields('t', ['entity_type', 'entity_id'])
      ->condition($col, $id)
      ->execute()->fetchAll();
    $out = [];
    foreach ($rows as $r) {
      foreach ($resolve($r->entity_type, (int) $r->entity_id, $seen) as $hit) {
        $hit['chain'] = "para:{$id}({$pi['bundle']}) -> " . $hit['chain'];
        $out[] = $hit;
      }
    }
    return $out ?: [['nid' => 0, 'title' => '(orphan paragraph ' . $id . ')', 'bundle' => '', 'chain' => "para:{$id}"]];
  }

  return [['nid' => 0, 'title' => "(unsupported host: {$type}:{$id})", 'bundle' => '', 'chain' => "{$type}:{$id}"]];
};

// Reverse map D7 fid -> D10 fid.
$d7_to_d10 = [];
foreach ($map as $d10_fid => $d7_fid) {
  $d7_to_d10[(int) $d7_fid] = (int) $d10_fid;
}

// Get D7 filenames.
$filenames = $d7->select('file_managed', 'f')
  ->fields('f', ['fid', 'filename', 'uri'])
  ->condition('fid', $d7_fids, 'IN')
  ->execute()->fetchAllAssoc('fid');

$rows = [];
foreach ($usage as $u) {
  $d7_fid = (int) $u->fid;
  $d10_fid = $d7_to_d10[$d7_fid] ?? 0;
  $fname = $filenames[$d7_fid]->filename ?? '';
  $uri   = $filenames[$d7_fid]->uri ?? '';
  foreach ($resolve($u->type, (int) $u->id) as $hit) {
    $rows[] = [
      'd7_fid'    => $d7_fid,
      'd10_fid'   => $d10_fid,
      'filename'  => $fname,
      'uri'       => $uri,
      'nid'       => $hit['nid'],
      'title'     => $hit['title'],
      'node_type' => $hit['bundle'],
      'host'      => $hit['chain'],
      'module'    => $u->module,
    ];
  }
}

// Deduplicate.
$seen = [];
$out = [];
foreach ($rows as $r) {
  $k = implode('|', $r);
  if (isset($seen[$k])) continue;
  $seen[$k] = TRUE;
  $out[] = $r;
}
usort($out, fn($a, $b) => [$a['d7_fid'], $a['nid']] <=> [$b['d7_fid'], $b['nid']]);

// Print + CSV.
$fh = $out_path ? fopen($out_path, 'w') : NULL;
if ($fh) fputcsv($fh, array_keys($out[0] ?? ['d7_fid','d10_fid','filename','uri','nid','title','node_type','host','module']));

echo str_repeat('-', 140) . "\n";
printf("%-8s %-8s %-7s %-20s %-50s %s\n", 'd7_fid', 'd10_fid', 'nid', 'node_type', 'title (truncated)', 'host chain');
echo str_repeat('-', 140) . "\n";
foreach ($out as $r) {
  printf("%-8d %-8d %-7d %-20s %-50s %s\n",
    $r['d7_fid'], $r['d10_fid'], $r['nid'], substr($r['node_type'], 0, 20),
    substr($r['title'], 0, 50), $r['host']);
  if ($fh) fputcsv($fh, $r);
}
echo str_repeat('-', 140) . "\n";

// Files with no node reference anywhere.
$used_d7_fids = array_unique(array_column($out, 'd7_fid'));
$unused_d7 = array_diff($d7_fids, $used_d7_fids);
echo "\nFiles referenced by at least one entity in D7: " . count($used_d7_fids) . "\n";
echo "Files with NO D7 file_usage entry (orphaned uploads): " . count($unused_d7) . "\n";
if ($unused_d7) {
  echo "  D7 fids: " . implode(', ', $unused_d7) . "\n";
}

if ($fh) fclose($fh);
if ($out_path) echo "\nWritten: {$out_path}\n";
