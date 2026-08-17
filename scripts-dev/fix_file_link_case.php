<?php

/**
 * @file
 * Correct content links whose case does not match the file on disk.
 *
 * The local DDEV volume is case-insensitive, Acquia's is not, so a link to
 * `.../white_pine_weevil_damage_4.JPG` loads locally and 404s on dev, stage
 * and prod when the file is really `..._damage_4.jpg`. This rewrites the
 * link to the on-disk spelling wherever it appears — body and other long
 * text, link-field URIs, menu links, and the serialized Layout Builder
 * sections.
 *
 * The substitution only changes letter case, so every replacement is the same
 * byte length; that is what makes it safe to apply inside serialized Layout
 * Builder data. MySQL's REPLACE() is case-sensitive, so the lowercase
 * occurrences are left alone.
 *
 * Reads scripts-dev/unmanaged/case_mismatch_links.tsv (linked_path, actual_path)
 * produced by scripts-dev/report_file_link_case.php. Idempotent: re-running
 * finds nothing to change.
 *
 * Usage: drush scr scripts-dev/fix_file_link_case.php
 *        drush scr scripts-dev/fix_file_link_case.php -- --dry-run
 */

$dry = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$tsv = DRUPAL_ROOT . '/../scripts-dev/unmanaged/case_mismatch_links.tsv';
if (!file_exists($tsv)) {
  print "Missing $tsv — run scripts-dev/report_file_link_case.php first.\n";
  return;
}
$pairs = [];
foreach (file($tsv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $line) {
  if ($i === 0 && str_starts_with($line, 'linked_path')) {
    continue;
  }
  [$wrong, $right] = array_pad(explode("\t", $line), 2, NULL);
  if ($wrong && $right && $wrong !== $right && strlen($wrong) === strlen($right)) {
    $pairs[$wrong] = $right;
  }
}
printf("%d case corrections to apply%s\n", count($pairs), $dry ? ' (dry run)' : '');

$db = \Drupal::database();
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
")->fetchAll();

$total = 0;
$touched = [];
foreach ($columns as $col) {
  if (!$schema->tableExists($col->t)) {
    continue;
  }
  foreach ($pairs as $wrong => $right) {
    $hits = $db->query(
      'SELECT COUNT(*) FROM {' . $col->t . '} WHERE INSTR(BINARY `' . $col->c . '`, BINARY :n) > 0',
      [':n' => $wrong]
    )->fetchField();
    if (!$hits) {
      continue;
    }
    $total += $hits;
    $touched[$col->t . '.' . $col->c][$wrong] = (int) $hits;
    if (!$dry) {
      $db->query(
        'UPDATE {' . $col->t . '} SET `' . $col->c . '` = REPLACE(`' . $col->c . '`, :w, :r) WHERE INSTR(BINARY `' . $col->c . '`, BINARY :w) > 0',
        [':w' => $wrong, ':r' => $right]
      );
    }
  }
}

foreach ($touched as $where => $items) {
  foreach ($items as $wrong => $n) {
    printf("  %-46s %d row(s)  %s\n", $where, $n, $wrong);
  }
}
printf("%s %d row(s)\n", $dry ? 'Would update' : 'Updated', $total);
if (!$dry && $total) {
  \Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list', 'block_content_list', 'paragraph_list']);
  print "Invalidated content cache tags — run `drush cr` for a full rebuild.\n";
}
