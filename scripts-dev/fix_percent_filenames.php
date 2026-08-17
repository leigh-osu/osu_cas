<?php

/**
 * @file
 * Rename files whose names contain literal percent-escapes.
 *
 * D7 stored some uploads with URL-escapes baked into the filename
 * ("OSU%20environmental%20...jpg"). Their derivative URLs double-encode
 * (%20 -> %2520) and the image-style pipeline 500s, so listings show no
 * picture. Decode the escapes (and swap characters that don't belong in
 * filenames for '-'), rename on disk, and update the file entity; media
 * labels carrying the old name follow. Idempotent; runs each rebuild since
 * the D7 source re-copies the original names.
 *
 * Usage: drush scr scripts-dev/fix_percent_filenames.php
 */

$fs = \Drupal::service('file_system');
$storage = \Drupal::entityTypeManager()->getStorage('file');
$fids = \Drupal::database()->query("SELECT fid FROM {file_managed} WHERE uri LIKE '%\\%%'")->fetchCol();
$renamed = $missing = 0;
foreach ($storage->loadMultiple($fids) as $file) {
  $uri = $file->getFileUri();
  $dir = dirname($uri);
  $base = basename($uri);
  $decoded = rawurldecode($base);
  // Encoded slashes decode to path separators; flatten them -- the file
  // lives in $dir, not in directories its name pretends to have.
  $decoded = str_replace('/', '-', $decoded);
  // Filesystem-hostile characters from the decode become hyphens.
  $decoded = preg_replace('~[|?#&<>"*:\\\\]|\'~', '-', $decoded);
  $decoded = preg_replace('/\s+/', ' ', trim($decoded));
  if ($decoded === $base) {
    continue;
  }
  $new_uri = $dir . '/' . $decoded;
  if (!file_exists($uri)) {
    print "MISSING on disk: $uri\n";
    $missing++;
    continue;
  }
  if (file_exists($new_uri)) {
    $info = pathinfo($decoded);
    $decoded = $info['filename'] . '-' . $file->id() . (isset($info['extension']) ? '.' . $info['extension'] : '');
    $new_uri = $dir . '/' . $decoded;
  }
  if (!rename($fs->realpath($uri), $fs->realpath($dir) . '/' . $decoded)) {
    print "RENAME FAILED: $uri\n";
    continue;
  }
  $file->setFileUri($new_uri);
  $file->setFilename($decoded);
  $file->save();
  // Media items named after the raw file follow the rename.
  $mids = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('name', $base)->execute();
  foreach (\Drupal::entityTypeManager()->getStorage('media')->loadMultiple($mids) as $media) {
    $media->setName($decoded);
    $media->save();
  }
  $renamed++;
}
printf("Renamed: %d  Missing on disk: %d  (candidates: %d)\n", $renamed, $missing, count($fids));
