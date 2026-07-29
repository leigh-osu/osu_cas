<?php
/**
 * @file
 * For each broken media:image entity, find every node that references it
 * (directly or via a paragraph / other intermediate entity).
 *
 * Expects a CSV previously produced by find_broken_images.php with columns:
 *   mid,fid,uri,real_path,reason
 *
 * @code
 * drush scr drush/scripts/find_broken_image_usage.php -- /tmp/broken_images.csv /tmp/broken_usage.csv
 * @endcode
 */

$in_path  = $extra[0] ?? '/tmp/broken_images.csv';
$out_path = $extra[1] ?? NULL;

if (!file_exists($in_path)) {
  echo "Input CSV not found: {$in_path}\n";
  return;
}

// Load broken media IDs from CSV.
$broken = [];
$fh_in = fopen($in_path, 'r');
fgetcsv($fh_in); // header
while (($row = fgetcsv($fh_in)) !== FALSE) {
  $broken[(int) $row[0]] = [
    'mid' => (int) $row[0],
    'fid' => (int) $row[1],
    'uri' => $row[2],
  ];
}
fclose($fh_in);

echo "Loaded " . count($broken) . " broken media entities.\n";

$efm = \Drupal::service('entity_field.manager');
$etm = \Drupal::entityTypeManager();
$db  = \Drupal::database();

// Discover every field (any entity type / bundle) that references media.
$media_ref_fields = [];
foreach (array_keys($etm->getDefinitions()) as $entity_type) {
  if (!$etm->getDefinition($entity_type)->entityClassImplements(\Drupal\Core\Entity\FieldableEntityInterface::class)) {
    continue;
  }
  $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);
  foreach (array_keys($bundle_info) as $bundle) {
    foreach ($efm->getFieldDefinitions($entity_type, $bundle) as $field_name => $def) {
      if ($def->getType() !== 'entity_reference') continue;
      $target = $def->getSetting('target_type');
      if ($target !== 'media') continue;
      $media_ref_fields[$entity_type][$field_name] = TRUE;
    }
  }
}

echo "Media reference fields by entity type:\n";
foreach ($media_ref_fields as $etype => $fields) {
  echo "  {$etype}: " . implode(', ', array_keys($fields)) . "\n";
}
echo "\n";

$broken_mids = array_keys($broken);

// For each entity type, for each media reference field, find rows that point
// at a broken media entity.
$direct_hits = []; // [entity_type][entity_id] = [mids...]
foreach ($media_ref_fields as $entity_type => $fields) {
  $storage = $etm->getStorage($entity_type);
  foreach (array_keys($fields) as $field_name) {
    $table = $entity_type . '__' . $field_name;
    if (!$db->schema()->tableExists($table)) continue;
    $col = $field_name . '_target_id';
    $query = $db->select($table, 't')
      ->fields('t', ['entity_id', $col])
      ->condition($col, $broken_mids, 'IN');
    foreach ($query->execute() as $row) {
      $direct_hits[$entity_type][$row->entity_id][] = (int) $row->{$col};
    }
  }
}

echo "Direct references:\n";
foreach ($direct_hits as $etype => $ids) {
  echo "  {$etype}: " . count($ids) . " entities\n";
}
echo "\n";

// Walk up non-node hits (paragraphs, media, etc.) to their owning node.
$resolved = []; // rows: [nid, node_title, mid, fid, uri, via_entity_type, via_entity_id]

$resolve_to_nodes = function (string $entity_type, int $entity_id, array $visited = []) use (&$resolve_to_nodes, $etm, $db): array {
  if ($entity_type === 'node') {
    return [[$entity_id, 'direct']];
  }
  $key = "{$entity_type}:{$entity_id}";
  if (isset($visited[$key])) return [];
  $visited[$key] = TRUE;

  // Paragraphs carry parent_type / parent_id in their base table.
  if ($entity_type === 'paragraph') {
    $row = $db->select('paragraphs_item_field_data', 'p')
      ->fields('p', ['parent_type', 'parent_id'])
      ->condition('id', $entity_id)
      ->execute()->fetchAssoc();
    if ($row && $row['parent_type']) {
      return $resolve_to_nodes($row['parent_type'], (int) $row['parent_id'], $visited);
    }
    return [];
  }

  // Generic: use file_usage-like reverse lookup — scan every entity_reference
  // field pointing at this entity type and see who references this id.
  $results = [];
  $efm = \Drupal::service('entity_field.manager');
  foreach (array_keys($etm->getDefinitions()) as $parent_et) {
    $def = $etm->getDefinition($parent_et);
    if (!$def->entityClassImplements(\Drupal\Core\Entity\FieldableEntityInterface::class)) continue;
    $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($parent_et);
    foreach (array_keys($bundle_info) as $bundle) {
      foreach ($efm->getFieldDefinitions($parent_et, $bundle) as $fname => $fdef) {
        if ($fdef->getType() !== 'entity_reference') continue;
        if ($fdef->getSetting('target_type') !== $entity_type) continue;
        $table = $parent_et . '__' . $fname;
        if (!$db->schema()->tableExists($table)) continue;
        $col = $fname . '_target_id';
        $rows = $db->select($table, 't')
          ->fields('t', ['entity_id'])
          ->condition($col, $entity_id)
          ->execute()->fetchCol();
        foreach ($rows as $pid) {
          foreach ($resolve_to_nodes($parent_et, (int) $pid, $visited) as $hit) {
            $results[] = $hit;
          }
        }
      }
    }
  }
  return $results;
};

$node_storage = $etm->getStorage('node');
$title_cache = [];
$get_title = function (int $nid) use ($node_storage, &$title_cache) {
  if (!isset($title_cache[$nid])) {
    $n = $node_storage->load($nid);
    $title_cache[$nid] = $n ? $n->label() : '(missing)';
  }
  return $title_cache[$nid];
};

foreach ($direct_hits as $entity_type => $ids) {
  foreach ($ids as $entity_id => $mids) {
    $mids = array_unique($mids);
    $paths = $resolve_to_nodes($entity_type, (int) $entity_id);
    if (empty($paths)) {
      foreach ($mids as $mid) {
        $resolved[] = [
          '',
          '(unreferenced by any node)',
          $mid,
          $broken[$mid]['fid'],
          $broken[$mid]['uri'],
          $entity_type,
          $entity_id,
        ];
      }
      continue;
    }
    foreach ($paths as [$nid, $via]) {
      foreach ($mids as $mid) {
        $resolved[] = [
          $nid,
          $get_title((int) $nid),
          $mid,
          $broken[$mid]['fid'],
          $broken[$mid]['uri'],
          $entity_type,
          $entity_id,
        ];
      }
    }
  }
}

// Deduplicate.
$seen = [];
$rows = [];
foreach ($resolved as $r) {
  $k = implode('|', $r);
  if (isset($seen[$k])) continue;
  $seen[$k] = TRUE;
  $rows[] = $r;
}

usort($rows, fn($a, $b) => [$a[2], $a[0]] <=> [$b[2], $b[0]]);

$fh = $out_path ? fopen($out_path, 'w') : NULL;
if ($fh) {
  fputcsv($fh, ['nid', 'node_title', 'mid', 'fid', 'uri', 'via_entity_type', 'via_entity_id']);
}

echo "\nBroken images and the nodes that reference them:\n";
echo str_repeat('-', 100) . "\n";
$orphans = 0;
foreach ($rows as $r) {
  if ($r[0] === '') $orphans++;
  printf("mid=%-6s fid=%-6s nid=%-7s via=%-10s %s | %s\n",
    $r[2], $r[3], $r[0] ?: '-', $r[5], $r[4], $r[1]);
  if ($fh) fputcsv($fh, $r);
}
if ($fh) fclose($fh);

echo str_repeat('-', 100) . "\n";
echo "Total rows:          " . count($rows) . "\n";
echo "Unreferenced media:  {$orphans}\n";
if ($out_path) echo "Written:             {$out_path}\n";
