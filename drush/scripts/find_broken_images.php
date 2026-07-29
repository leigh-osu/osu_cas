<?php
/**
 * @file
 * Scan migrated media:image entities and report files that are corrupt,
 * missing, or unreadable by PHP's image functions.
 *
 * @code
 * drush scr drush/scripts/find_broken_images.php
 * drush scr drush/scripts/find_broken_images.php --script-args=/tmp/broken.csv
 * @endcode
 */

$out_path = $extra[0] ?? NULL;
$fh = $out_path ? fopen($out_path, 'w') : NULL;
if ($fh) {
  fputcsv($fh, ['mid', 'fid', 'uri', 'real_path', 'reason']);
}

$storage = \Drupal::entityTypeManager()->getStorage('media');
$file_system = \Drupal::service('file_system');

$mids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('bundle', 'image')
  ->execute();

$total = count($mids);
$checked = 0;
$broken = 0;
$missing = 0;

echo "Scanning {$total} media:image entities...\n";

foreach (array_chunk($mids, 200) as $chunk) {
  foreach ($storage->loadMultiple($chunk) as $media) {
    $checked++;
    if (!$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      continue;
    }
    $file = $media->get('field_media_image')->entity;
    if (!$file) {
      continue;
    }
    $uri = $file->getFileUri();
    $real = $file_system->realpath($uri);

    $reason = NULL;
    if (!$real || !file_exists($real)) {
      $reason = 'missing';
      $missing++;
    }
    else {
      $info = @getimagesize($real);
      if ($info === FALSE) {
        $reason = 'unreadable (corrupt header or truncated)';
        $broken++;
      }
    }

    if ($reason) {
      $line = sprintf("[%s] mid=%d fid=%d %s\n", $reason, $media->id(), $file->id(), $uri);
      echo $line;
      if ($fh) {
        fputcsv($fh, [$media->id(), $file->id(), $uri, $real ?: '', $reason]);
      }
    }
  }
  if ($checked % 2000 === 0) {
    echo "  ...checked {$checked}/{$total}\n";
  }
}

if ($fh) {
  fclose($fh);
}

echo "\nDone.\n";
echo "Checked:  {$checked}\n";
echo "Missing:  {$missing}\n";
echo "Corrupt:  {$broken}\n";
if ($out_path) {
  echo "Written:  {$out_path}\n";
}
