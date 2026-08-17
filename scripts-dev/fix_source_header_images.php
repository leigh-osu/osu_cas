<?php

/**
 * @file
 * Knock the white background out of The Source header images.
 *
 * The D7 masthead pair (the_source.gif, news_osu_agsci-text.png) is flat
 * artwork on white; on manzanita's off-white page ground they show as
 * white boxes. Rebuild each as an alpha PNG: a pixel's opacity is its
 * distance from white (so anti-aliased edges stay smooth) and its colour is
 * un-blended from the white it was composited on. The GIF becomes a PNG
 * (file entity uri/filename/mime updated) since GIF has no partial alpha.
 * Image-style derivatives are flushed so the .webp copies regenerate.
 *
 * Idempotent: a file already ending in .png with an alpha channel that has
 * transparent corners is left alone.
 *
 * Usage: drush scr scripts-dev/fix_source_header_images.php
 */

use Drupal\file\Entity\File;

$targets = ['lp/picbox/the_source.gif', 'lp/picbox/news_osu_agsci-text.png'];
$fs = \Drupal::service('file_system');
$style_storage = \Drupal::entityTypeManager()->getStorage('image_style');

foreach ($targets as $rel) {
  $uri = 'public://' . $rel;
  $files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $uri]);
  $file = $files ? reset($files) : NULL;
  $path = $fs->realpath($uri);
  if (!$file || !$path || !file_exists($path)) {
    // Already converted on an earlier run?
    $png_uri = preg_replace('/\.gif$/', '.png', $uri);
    if ($png_uri !== $uri && \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $png_uri])) {
      print "OK   $rel already converted\n";
      continue;
    }
    print "SKIP $rel: file or entity missing\n";
    continue;
  }

  $src = str_ends_with($path, '.gif') ? imagecreatefromgif($path) : imagecreatefrompng($path);
  if (!$src) {
    print "SKIP $rel: unreadable\n";
    continue;
  }
  $w = imagesx($src);
  $h = imagesy($src);

  // Idempotence: transparent corners already.
  if (str_ends_with($path, '.png')) {
    $c = imagecolorsforindex($src, imagecolorat($src, 0, 0));
    if (($c['alpha'] ?? 0) >= 120) {
      print "OK   $rel already transparent\n";
      imagedestroy($src);
      continue;
    }
  }

  $dst = imagecreatetruecolor($w, $h);
  imagealphablending($dst, FALSE);
  imagesavealpha($dst, TRUE);
  for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
      $c = imagecolorsforindex($src, imagecolorat($src, $x, $y));
      $r = $c['red'];
      $g = $c['green'];
      $b = $c['blue'];
      // Opacity = how far the pixel is from white; colour un-blended from
      // white at that opacity.
      $alpha = 255 - min($r, $g, $b);
      if ($alpha <= 0) {
        $col = imagecolorallocatealpha($dst, 255, 255, 255, 127);
      }
      else {
        $un = fn($v) => max(0, min(255, (int) round(255 - (255 - $v) * 255 / $alpha)));
        // GD alpha: 0 opaque .. 127 transparent.
        $col = imagecolorallocatealpha($dst, $un($r), $un($g), $un($b), (int) round((255 - $alpha) * 127 / 255));
      }
      imagesetpixel($dst, $x, $y, $col);
    }
  }
  imagedestroy($src);

  $new_uri = preg_replace('/\.gif$/', '.png', $uri);
  $new_path = $fs->realpath(dirname($new_uri)) . '/' . basename($new_uri);
  imagepng($dst, $new_path, 9);
  imagedestroy($dst);

  if ($new_uri !== $uri) {
    @unlink($path);
    $file->setFileUri($new_uri);
    $file->setFilename(basename($new_uri));
    $file->setMimeType('image/png');
  }
  $file->setSize(filesize($new_path));
  $file->save();

  foreach ($style_storage->loadMultiple() as $style) {
    $style->flush($uri);
    if ($new_uri !== $uri) {
      $style->flush($new_uri);
    }
  }
  print "DONE $rel -> " . basename($new_uri) . " ({$w}x{$h})\n";
}
