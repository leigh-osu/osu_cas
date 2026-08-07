<?php

/**
 * @file
 * Resolve duplicate path aliases in favour of what live D7 serves.
 *
 * Migration + pathauto left nine aliases owned by two entities (mostly a
 * taxonomy term page shadowing the real node: /source/students rendered
 * the_source term 17656 instead of node 4386). Inbound alias lookup picks
 * the oldest row, which loses in every D7-verified case. Disable every
 * status-1 row for these aliases that does not point at the D7-verified
 * target (winners checked against live D7 2026-08-07; the two unverifiable
 * pairs are same-content duplicates where the later row wins for
 * consistency). Idempotent; runs in rebuild_site.sh section 7.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_duplicate_aliases.php
 */

$winners = [
  '/cbarc/agronomy' => '/node/101551',
  '/cbarc/plant-pathology' => '/node/101631',
  '/cbarc/weeds' => '/node/101526',
  '/source/students' => '/node/4386',
  '/blogs' => '/node/24717',
  '/publication-authors/weiss-carsten' => '/taxonomy/term/28538',
  '/eoarc/jeremy-james' => '/node/100061',
  '/weather/malheur-experiment-station/september-14-2021' => '/node/235526',
  '/art/artwork/1994/gardeners' => '/node/252416',
];

$storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$disabled = 0;
foreach ($winners as $alias => $target) {
  $ids = \Drupal::database()->query("SELECT id FROM {path_alias} WHERE alias = :a AND status = 1 AND path != :p", [':a' => $alias, ':p' => $target])->fetchCol();
  foreach ($storage->loadMultiple($ids) as $entity) {
    // NB: setPublished() is a silent no-op on path_alias entities; set the
    // status field directly.
    $entity->set('status', 0);
    $entity->save();
    $disabled++;
    print "disabled {$entity->id()}: $alias -> {$entity->getPath()} (winner: $target)\n";
  }
}
print "Done: $disabled shadowing alias rows disabled.\n";
