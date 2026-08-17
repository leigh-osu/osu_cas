<?php

/**
 * @file
 * Report files that content links to which exist on disk but have no file
 * entity — i.e. still outside the managed-files system.
 *
 * Scans every long-text field on every fieldable entity, the serialized
 * Layout Builder sections, all link-field URI columns and the menu links for
 * href/src/uri targets; keeps the ones that resolve into the public (or
 * private) files directory; and classifies each as managed, unmanaged, or
 * missing on disk. D7 used several interchangeable path prefixes
 * (sites/default, sites/agsci, sites/agscid7, bare /files), so each is tried
 * against the real public files root before a path is called missing.
 *
 * Writes scripts-dev/unmanaged/referenced_unmanaged.txt (paths relative to
 * the public files root) for use as an rsync/registration work list.
 *
 * Usage: drush scr scripts-dev/report_unmanaged_referenced_files.php
 */

$db = \Drupal::database();
$fs = \Drupal::service('file_system');
$public = rtrim($fs->realpath('public://'), '/') . '/';
$private_real = $fs->realpath('private://');
$private = $private_real ? rtrim($private_real, '/') . '/' : NULL;

// ---------------------------------------------------------------- collect
$targets = [];
$add = function (string $raw) use (&$targets) {
  $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
  $raw = strtok($raw, '#');
  $raw = strtok($raw, '?');
  if ($raw === FALSE || $raw === '') {
    return;
  }
  // Absolute URLs: keep only our own hosts.
  if (preg_match('~^(https?:)?//([^/]+)(/.*)?$~i', $raw, $m)) {
    $host = strtolower($m[2]);
    if (!preg_match('~(oregonstate\.edu|spottedwing\.org|infews\.org|open-sensing\.org|ddev\.site)$~', $host)) {
      return;
    }
    $raw = $m[3] ?? '/';
  }
  if (preg_match('~^(mailto|tel|data|javascript):~i', $raw)) {
    return;
  }
  $targets[rawurldecode($raw)] = TRUE;
};

$schema = $db->schema();
$columns = $db->query("
  SELECT TABLE_NAME AS t, COLUMN_NAME AS c
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND DATA_TYPE IN ('longtext', 'text', 'mediumtext', 'varchar')
    AND (COLUMN_NAME LIKE '%_value' OR COLUMN_NAME LIKE '%_uri' OR COLUMN_NAME = 'link__uri'
         OR COLUMN_NAME = 'layout_builder__layout_section')
    AND TABLE_NAME NOT LIKE 'migrate_%'
    AND TABLE_NAME NOT LIKE 'cache%'
    AND TABLE_NAME NOT LIKE 'search_%'
    AND TABLE_NAME NOT LIKE '%revision%'
    AND TABLE_NAME NOT LIKE '%meta_tags%'
")->fetchAll();

$scanned = 0;
foreach ($columns as $col) {
  if (!$schema->tableExists($col->t)) {
    continue;
  }
  foreach ($db->query('SELECT `' . $col->c . '` AS v FROM {' . $col->t . '}') as $row) {
    if ($row->v === NULL || $row->v === '') {
      continue;
    }
    $scanned++;
    if (preg_match_all('~(?:href|src)\s*=\s*"([^"]+)"~i', $row->v, $m)) {
      foreach ($m[1] as $u) {
        $add($u);
      }
    }
    if (preg_match_all("~(?:href|src)\s*=\s*'([^']+)'~i", $row->v, $m)) {
      foreach ($m[1] as $u) {
        $add($u);
      }
    }
    // Bare link-field / menu URIs.
    if (preg_match('~^(internal:|base:|entity:|/|https?://|//)~i', trim($row->v))) {
      $add(preg_replace('~^(internal:|base:)~', '', trim($row->v)));
    }
  }
}

// ---------------------------------------------------------------- classify
$managed = [];
foreach ($db->query("SELECT uri FROM {file_managed}") as $row) {
  $managed[$row->uri] = TRUE;
}

$PREFIXES = [
  '~^/?sites/[^/]+/files/~',
  '~^/?files/~',
];
$unmanaged = $missing = $ok = $private_hits = 0;
$list = [];
foreach (array_keys($targets) as $path) {
  $rel = NULL;
  if (preg_match('~^/?system/files/(.+)$~', $path, $m)) {
    if ($private && file_exists($private . $m[1])) {
      $private_hits++;
      if (!isset($managed['private://' . $m[1]])) {
        $unmanaged++;
        $list[] = 'private://' . $m[1];
      }
    }
    continue;
  }
  foreach ($PREFIXES as $re) {
    if (preg_match($re, $path)) {
      $rel = preg_replace($re, '', $path);
      break;
    }
  }
  if ($rel === NULL || $rel === '') {
    continue;
  }
  $rel = ltrim($rel, '/');
  if (!file_exists($public . $rel)) {
    $missing++;
    continue;
  }
  if (isset($managed['public://' . $rel])) {
    $ok++;
    continue;
  }
  $unmanaged++;
  $list[] = $rel;
}

sort($list);
$out = DRUPAL_ROOT . '/../scripts-dev/unmanaged/referenced_unmanaged.txt';
file_put_contents($out, implode("\n", $list) . "\n");

printf("field values scanned:            %d\n", $scanned);
printf("unique link targets:             %d\n", count($targets));
printf("file links resolving on disk:    %d managed, %d UNMANAGED\n", $ok, $unmanaged);
printf("file links with nothing on disk: %d\n", $missing);
printf("private-file links on disk:      %d\n", $private_hits);
printf("wrote %s (%d paths)\n", $out, count($list));
