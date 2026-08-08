<?php

/**
 * @file
 * Replace the global imagemagick '-auto-orient' prepend with a per-style
 * auto-orient effect.
 *
 * The global prepend is applied to every binary the toolkit runs --
 * including `identify`, which on ImageMagick 6 (Acquia) rejects
 * `-auto-orient`, so EVERY fresh derivative fails on stage/prod while
 * IM7 (ddev) tolerates it. The image_effects module's auto-orient effect
 * does the same rotation but only during convert, on both IM versions.
 * Sets prepend to '', adds image_effects_auto_orient as the first effect
 * of every image style that lacks it, and flushes styles. Idempotent.
 *
 * Run (local): ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_imagemagick_orientation.php
 * (also run on stage via files-private copy)
 */

use Drupal\image\Entity\ImageStyle;

$config = \Drupal::configFactory()->getEditable('imagemagick.settings');
if ($config->get('prepend') !== '') {
  $config->set('prepend', '')->save();
  print "imagemagick prepend cleared\n";
}

$added = 0;
foreach (ImageStyle::loadMultiple() as $style) {
  $has = FALSE;
  foreach ($style->getEffects() as $effect) {
    if ($effect->getPluginId() === 'image_effects_auto_orient') {
      $has = TRUE;
      break;
    }
  }
  if (!$has) {
    $style->addImageEffect([
      'id' => 'image_effects_auto_orient',
      'weight' => -100,
      'data' => ['scan_exif' => TRUE],
    ]);
    $style->save();
    $added++;
  }
}
print "auto-orient effect added to $added styles\n";
foreach (ImageStyle::loadMultiple() as $style) {
  $style->flush();
}
print "styles flushed\n";
