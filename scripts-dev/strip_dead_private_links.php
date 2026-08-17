<?php

/**
 * @file
 * Strip links to D6/D7 private-file artefacts that can never resolve.
 *
 * D7 served private files through /system/files/. Two families of those paths
 * survive in migrated content and have no possible target in D10:
 *   /system/files/u<uid>/...      per-user upload folders
 *   /system/files/imagecache/...  D6-era image derivative URLs
 * Neither exists in the D10 tree, in the D7 private tree, or on D7 production
 * (spot-checked: every one 404s today), so there is nothing to restore.
 *
 * Rather than leave dead links in place this removes them in the way that
 * keeps the page readable:
 *   <a href="dead">text</a>  ->  text          (wording kept, link dropped)
 *   <img src="dead">         ->  removed       (broken image icon gone)
 *   link-field uri = dead    ->  field cleared
 *   redirect -> dead         ->  redirect deleted
 *
 * Only the exact link strings listed in scripts-dev/missing_file_links.csv are
 * touched, so nothing that still resolves can be affected. Idempotent.
 *
 * Usage: drush scr scripts-dev/strip_dead_private_links.php -- --dry-run
 *        drush scr scripts-dev/strip_dead_private_links.php
 */

$dry = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$csv = DRUPAL_ROOT . '/../scripts-dev/missing_file_links.csv';
if (!file_exists($csv)) {
  print "Missing $csv — run scripts-dev/report_missing_file_links.php first.\n";
  return;
}

// Collect the target references: private scheme, u<uid>/ or imagecache/.
$targets = [];
$fh = fopen($csv, 'r');
$head = fgetcsv($fh);
while (($row = fgetcsv($fh)) !== FALSE) {
  $r = array_combine($head, $row);
  if ($r['scheme'] !== 'private') {
    continue;
  }
  $rel = preg_replace('~^private://~', '', $r['file_path']);
  if (!preg_match('~^(u\d+|imagecache)/~', $rel)) {
    continue;
  }
  $targets[] = $r;
}
fclose($fh);
$links = array_values(array_unique(array_column($targets, 'raw_link')));
printf("%d references, %d distinct dead links%s\n", count($targets), count($links), $dry ? ' (dry run)' : '');

$db = \Drupal::database();
$schema = $db->schema();
$fields = [];
foreach ($targets as $t) {
  $fields[$t['field']][] = $t['raw_link'];
}

// Which tables carry those columns.
$columns = $db->query("
  SELECT TABLE_NAME AS t, COLUMN_NAME AS c
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND COLUMN_NAME IN (:cols[])
    AND TABLE_NAME NOT LIKE 'migrate_%'
    AND TABLE_NAME NOT LIKE 'cache%'
", [':cols[]' => array_keys($fields)])->fetchAll();

$unwrapped = $images = $cleared = $redirects = 0;
$examples = [];

foreach ($columns as $col) {
  if (!$schema->tableExists($col->t)) {
    continue;
  }
  $is_uri = str_ends_with($col->c, '_uri') || $col->c === 'link__uri';
  $key = $schema->fieldExists($col->t, 'entity_id') ? 'entity_id' : ($schema->fieldExists($col->t, 'rid') ? 'rid' : ($schema->fieldExists($col->t, 'id') ? 'id' : NULL));
  foreach ($fields[$col->c] ?? [] as $link) {
    $rows = $db->query('SELECT ' . ($key ? "`$key` AS k, " : 'NULL AS k, ') . '`' . $col->c . '` AS v FROM {' . $col->t . '} WHERE INSTR(`' . $col->c . '`, :n) > 0', [':n' => $link])->fetchAll();
    foreach ($rows as $row) {
      $before = $row->v;
      if ($is_uri && str_contains($before, $link)) {
        // A link field (or redirect) whose whole value is the dead target.
        if ($col->t === 'redirect') {
          $redirects++;
          if (!$dry) {
            $db->delete('redirect')->condition('rid', $row->k)->execute();
          }
        }
        else {
          $cleared++;
          if (!$dry) {
            $db->delete($col->t)->condition($key, $row->k)->execute();
          }
        }
        $examples[] = sprintf('%s #%s %s -> cleared', $col->t, $row->k, substr($link, 0, 70));
        continue;
      }
      $q = preg_quote($link, '~');
      $after = $before;
      // Images: drop the element entirely.
      $after = preg_replace('~<img\b[^>]*\bsrc\s*=\s*(["\'])' . $q . '\1[^>]*>~i', '', $after, -1, $n_img);
      // Anchors: keep the text, drop the link.
      $after = preg_replace('~<a\b[^>]*\bhref\s*=\s*(["\'])' . $q . '\1[^>]*>(.*?)</a>~is', '$2', $after, -1, $n_a);
      if ($after === $before) {
        continue;
      }
      $images += $n_img;
      $unwrapped += $n_a;
      if (count($examples) < 12) {
        $examples[] = sprintf('%s #%s %s (%d img, %d links)', $col->t, $row->k, substr($link, 0, 62), $n_img, $n_a);
      }
      if (!$dry) {
        $db->update($col->t)->fields([$col->c => $after])->condition($key, $row->k)->execute();
      }
    }
  }
}

foreach ($examples as $e) {
  print "  $e\n";
}
printf(
  "%s %d broken images, unwrapped %d links, cleared %d link fields, deleted %d redirects\n",
  $dry ? 'Would remove' : 'Removed', $images, $unwrapped, $cleared, $redirects
);
if (!$dry && ($images || $unwrapped || $cleared || $redirects)) {
  \Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list', 'block_content_list', 'paragraph_list']);
  print "Invalidated content cache tags — run `drush cr` for a full rebuild.\n";
}
