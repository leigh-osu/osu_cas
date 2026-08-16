<?php

/**
 * @file
 * Mark every profile "send to all affiliates" (visible on every domain).
 *
 * D7 profiles were user entities, never gated by Domain Access, so people
 * appeared in group listings on any domain. D10 profiles are nodes; without
 * this, a person assigned to cropandsoil vanishes from a horticulture
 * program page's people listing. Domain assignments still record where a
 * person belongs. One-time repair for the current database — rebuilds set
 * the flag in-migration and the field default covers new profiles.
 *
 * Usage: drush scr scripts-dev/backfill_profile_all_affiliates.php
 */

$storage = \Drupal::entityTypeManager()->getStorage('node');
$nids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'osu_profile')->execute();
$set = $already = 0;
foreach (array_chunk($nids, 50) as $chunk) {
  foreach ($storage->loadMultiple($chunk) as $node) {
    if (!empty($node->get('field_domain_all_affiliates')->value)) {
      $already++;
      continue;
    }
    $node->set('field_domain_all_affiliates', 1);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    $set++;
  }
  $storage->resetCache($chunk);
}
printf("Set: %d  Already set: %d  Total profiles: %d\n", $set, $already, count($nids));
