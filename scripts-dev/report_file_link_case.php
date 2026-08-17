<?php

/**
 * @file
 * Report content file links whose CASE does not match the file on disk.
 *
 * The local DDEV volume is case-insensitive (macOS), Acquia's is not. A link
 * to `.../white_pine_weevil_damage_4.JPG` therefore resolves locally and 404s
 * on dev/stage/prod when the file is really `..._4.jpg`. Nothing in a normal
 * QA pass on a Mac can see this class of breakage.
 *
 * Reads a case-exact listing of the public files tree (produced by the caller,
 * see below) and compares every file link found in content against it, first
 * exactly and then case-insensitively. A link that only matches
 * case-insensitively is a live 404 on Linux.
 *
 * Prepare the listing first:
 *   cd docroot/sites/agsci.oregonstate.edu/files \
 *     && find . -type f -not -path "./styles/*" | sed 's|^\./||' | sort \
 *      > ../../../../scripts-dev/unmanaged/d10_disk_files.txt
 *
 * Usage: drush scr scripts-dev/report_file_link_case.php
 */

$db = \Drupal::database();
$listing = DRUPAL_ROOT . '/../scripts-dev/unmanaged/d10_disk_files.txt';
if (!file_exists($listing)) {
  print "Missing $listing — see the header comment for how to generate it.\n";
  return;
}
$exact = [];
$lower = [];
foreach (file($listing, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $rel) {
  $exact[$rel] = TRUE;
  $lower[strtolower($rel)][] = $rel;
}

$targets = [];
$add = function (string $raw) use (&$targets) {
  $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
  $raw = strtok($raw, '#');
  $raw = strtok($raw, '?');
  if (!$raw) {
    return;
  }
  if (preg_match('~^(https?:)?//([^/]+)(/.*)?$~i', $raw, $m)) {
    if (!preg_match('~(oregonstate\.edu|spottedwing\.org|infews\.org|open-sensing\.org|ddev\.site)$~i', $m[2])) {
      return;
    }
    $raw = $m[3] ?? '/';
  }
  $raw = rawurldecode($raw);
  foreach (['~^/?sites/[^/]+/files/~', '~^/?files/~'] as $re) {
    if (preg_match($re, $raw)) {
      $rel = ltrim(preg_replace($re, '', $raw), '/');
      $rel = preg_replace('~/+~', '/', $rel);
      if ($rel !== '' && !str_starts_with($rel, 'styles/')) {
        $targets[$rel] = TRUE;
      }
      return;
    }
  }
};

$columns = $db->query("
  SELECT TABLE_NAME AS t, COLUMN_NAME AS c
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND DATA_TYPE IN ('longtext', 'text', 'mediumtext', 'varchar')
    AND (COLUMN_NAME LIKE '%_value' OR COLUMN_NAME LIKE '%_uri' OR COLUMN_NAME = 'link__uri'
         OR COLUMN_NAME = 'layout_builder__layout_section')
    AND TABLE_NAME NOT LIKE 'migrate_%' AND TABLE_NAME NOT LIKE 'cache%'
    AND TABLE_NAME NOT LIKE 'search_%' AND TABLE_NAME NOT LIKE '%revision%'
")->fetchAll();
$schema = $db->schema();
foreach ($columns as $col) {
  if (!$schema->tableExists($col->t)) {
    continue;
  }
  foreach ($db->query('SELECT `' . $col->c . '` AS v FROM {' . $col->t . '}') as $row) {
    if (!$row->v) {
      continue;
    }
    if (preg_match_all('~(?:href|src)\s*=\s*["\']([^"\']+)["\']~i', $row->v, $m)) {
      foreach ($m[1] as $u) {
        $add($u);
      }
    }
    if (preg_match('~^(internal:|base:|/)~i', trim($row->v))) {
      $add(preg_replace('~^(internal:|base:)~', '', trim($row->v)));
    }
  }
}

$ok = $mismatch = $absent = 0;
$rows = [];
foreach (array_keys($targets) as $rel) {
  if (isset($exact[$rel])) {
    $ok++;
    continue;
  }
  $key = strtolower($rel);
  if (isset($lower[$key])) {
    $mismatch++;
    $rows[] = [$rel, $lower[$key][0]];
    continue;
  }
  $absent++;
}
usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));
$out = DRUPAL_ROOT . '/../scripts-dev/unmanaged/case_mismatch_links.tsv';
$fh = fopen($out, 'w');
fwrite($fh, "linked_path\tactual_path\n");
foreach ($rows as $r) {
  fwrite($fh, $r[0] . "\t" . $r[1] . "\n");
}
fclose($fh);

printf("file links in content:     %d\n", count($targets));
printf("  exact match on disk:     %d\n", $ok);
printf("  CASE MISMATCH (404 on Linux): %d\n", $mismatch);
printf("  no file at all:          %d\n", $absent);
printf("wrote %s\n", $out);
foreach (array_slice($rows, 0, 15) as $r) {
  printf("  %s\n     -> %s\n", $r[0], $r[1]);
}
