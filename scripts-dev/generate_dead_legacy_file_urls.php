<?php

/**
 * @file
 * Regenerates scripts-dev/dead_legacy_file_urls.csv from the live database.
 *
 * Run with: ddev drush scr scripts-dev/generate_dead_legacy_file_urls.php
 * (rebuild_site.sh runs it automatically at the end of verification).
 *
 * Lists every remaining hardcoded /sites/agscid7/files/ URL in rich text
 * after CasLegacyFilePaths has done its work during migration:
 *  - missing-on-d7: the file exists neither in D10 nor on the D7 filesystem
 *    (dead on the old site too) — the content-cleanup list.
 *  - recoverable-next-rebuild: the file exists on D7; the reference was
 *    migrated before the current rewrite rules and converts on re-import.
 * The hosts column resolves each reference to the node/group whose layout
 * renders it (paragraphs are walked up to their host block); orphan-block/
 * orphan-paragraph entries render nowhere (see prune_orphan_blocks.php).
 */

use Drupal\Core\Site\Settings;

$db = \Drupal::database();
$d7_files = rtrim(Settings::get('cas_migrate_d7_files_path', '/var/www/d7/sites/agscid7/files'), '/');

$refs = [];
$tables = array_merge($db->schema()->findTables('paragraph__%'), ['node__body', 'block_content__body']);
foreach ($tables as $t) {
  if (str_contains($t, '_revision')) {
    continue;
  }
  $etype = str_starts_with($t, 'node__') ? 'node' : (str_starts_with($t, 'block_content__') ? 'block_content' : 'paragraph');
  foreach ($db->query("SHOW COLUMNS FROM {" . $t . "}")->fetchCol() as $col) {
    if (!str_ends_with($col, '_value')) {
      continue;
    }
    $rows = $db->query("SELECT entity_id, $col FROM {" . $t . "} WHERE $col LIKE :p", [':p' => '%/sites/agscid7/files/%'])->fetchAllKeyed();
    foreach ($rows as $id => $html) {
      foreach (['"', "'"] as $q) {
        if (preg_match_all('~(?<=' . $q . ')(?:https?://[^/' . $q . ']+)?/sites/agscid7/files/([^' . $q . '?#]+)~', (string) $html, $m)) {
          foreach ($m[1] as $rel) {
            $refs[$rel][] = [$etype, $id];
          }
        }
      }
    }
  }
}

$resolve_paragraph = function ($pid) use ($db) {
  $p = ['parent_id' => $pid, 'parent_type' => 'paragraph'];
  while ($p && $p['parent_type'] === 'paragraph') {
    $p = $db->query('SELECT parent_id, parent_type FROM {paragraphs_item_field_data} WHERE id = :id', [':id' => $p['parent_id']])->fetchAssoc();
  }
  return $p ? [$p['parent_type'], $p['parent_id']] : NULL;
};

$block_hosts = function ($bid) use ($db) {
  $hosts = [];
  foreach ($db->query('SELECT revision_id FROM {block_content_revision} WHERE id = :id', [':id' => $bid])->fetchCol() as $rev) {
    $needle = '%"block_revision_id";s:' . strlen((string) $rev) . ':"' . $rev . '"%';
    foreach ($db->query('SELECT entity_id FROM {node__layout_builder__layout} WHERE layout_builder__layout_section LIKE :n', [':n' => $needle])->fetchCol() as $nid) {
      $hosts["node:$nid"] = 1;
    }
    foreach ($db->query('SELECT entity_id FROM {group__layout_builder__layout} WHERE layout_builder__layout_section LIKE :n', [':n' => $needle])->fetchCol() as $gid) {
      $hosts["group:$gid"] = 1;
    }
  }
  return array_keys($hosts);
};

$out = fopen(DRUPAL_ROOT . '/../scripts-dev/dead_legacy_file_urls.csv', 'w');
fputcsv($out, ['legacy_url', 'status', 'hosts']);
ksort($refs);
$cache = [];
$counts = ['missing-on-d7' => 0, 'recoverable-next-rebuild' => 0];
foreach ($refs as $rel => $sources) {
  $status = file_exists($d7_files . '/' . rawurldecode($rel)) ? 'recoverable-next-rebuild' : 'missing-on-d7';
  $counts[$status]++;
  $hosts = [];
  foreach ($sources as [$etype, $id]) {
    if ($etype === 'paragraph') {
      $h = $resolve_paragraph($id);
      if (!$h) {
        $hosts["orphan-paragraph:$id"] = 1;
        continue;
      }
      [$etype, $id] = $h;
    }
    if ($etype === 'node') {
      $hosts["node:$id"] = 1;
    }
    elseif ($etype === 'block_content') {
      $cache[$id] = $cache[$id] ?? $block_hosts($id);
      if ($cache[$id]) {
        foreach ($cache[$id] as $h) {
          $hosts[$h] = 1;
        }
      }
      else {
        $hosts["orphan-block:$id"] = 1;
      }
    }
  }
  fputcsv($out, ['/sites/agscid7/files/' . $rel, $status, implode(';', array_keys($hosts))]);
}
fclose($out);
printf("dead_legacy_file_urls.csv: %d urls (%d missing-on-d7, %d recoverable)\n",
  array_sum($counts), $counts['missing-on-d7'], $counts['recoverable-next-rebuild']);
