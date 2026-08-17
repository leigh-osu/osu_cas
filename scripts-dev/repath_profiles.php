<?php

/**
 * @file
 * Give every profile one consistent URL: /directory/people/<name>.
 *
 * Profiles were split across two alias shapes because the group_content
 * pathauto pattern (/group/[group id]/...) beat the osu_profile pattern for
 * any profile placed in a group -- which is nearly all of them:
 *   2,016 at /group/<gid>/<name>
 *     201 at /directory/<name>
 *
 * This sets the profile pattern to /directory/people/[node:title], removes the
 * group_content pattern so group placement no longer dictates URLs, then
 * regenerates every profile alias. Each old alias becomes a redirect, so
 * existing links and bookmarks keep working.
 *
 * Idempotent: profiles already on the new path are left alone, and redirects
 * are only created where one does not already exist.
 *
 * Usage: drush scr scripts-dev/repath_profiles.php -- --dry-run
 *        drush scr scripts-dev/repath_profiles.php
 */

use Drupal\redirect\Entity\Redirect;

$dry = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$new_pattern = '/directory/people/[node:title]';

$storage = \Drupal::entityTypeManager()->getStorage('pathauto_pattern');
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$generator = \Drupal::service('pathauto.generator');
$db = \Drupal::database();

// 1. Point the profile pattern at the new path.
$pattern = $storage->load('osu_profile_node');
if (!$pattern) {
  print "osu_profile_node pattern not found\n";
  return;
}
printf("profile pattern: %s -> %s\n", $pattern->getPattern(), $new_pattern);
if (!$dry && $pattern->getPattern() !== $new_pattern) {
  $pattern->setPattern($new_pattern)->save();
}

// 2. Drop the group_content pattern so group placement stops setting URLs.
$group_pattern = $storage->load('group_content');
if ($group_pattern) {
  printf("deleting pathauto pattern group_content (%s)\n", $group_pattern->getPattern());
  if (!$dry) {
    $group_pattern->delete();
  }
}
else {
  print "group_content pattern already gone\n";
}

// 3. Regenerate every profile alias, leaving a redirect behind.
$nids = $db->query("SELECT nid FROM {node_field_data} WHERE type = 'osu_profile'")->fetchCol();
printf("profiles: %d\n", count($nids));

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$moved = $already = $failed = $redirects = 0;
$samples = [];
foreach (array_chunk($nids, 200) as $chunk) {
  foreach ($node_storage->loadMultiple($chunk) as $node) {
    $system = '/node/' . $node->id();
    // loadByProperties() returns entities keyed by id, so re-index: the
    // "already correct" test and the samples below index from 0.
    $old = array_values(array_map(
      fn($a) => $a->getAlias(),
      $alias_storage->loadByProperties(['path' => $system])
    ));
    // Already correct and nothing stale to clear?
    $wanted_prefix = '/directory/people/';
    if (count($old) === 1 && str_starts_with($old[0], $wanted_prefix)) {
      $already++;
      continue;
    }
    if ($dry) {
      $moved++;
      if (count($samples) < 6) {
        $samples[] = ($old[0] ?? '(none)') . '  ->  ' . $wanted_prefix . '…';
      }
      continue;
    }
    // Drop the existing aliases, then let pathauto build the new one.
    foreach ($alias_storage->loadByProperties(['path' => $system]) as $alias_entity) {
      $alias_entity->delete();
    }
    $result = $generator->updateEntityAlias($node, 'bulkupdate', ['force' => TRUE]);
    if (!$result) {
      $failed++;
      continue;
    }
    $new = $result['alias'];
    $moved++;
    if (count($samples) < 6) {
      $samples[] = ($old[0] ?? '(none)') . '  ->  ' . $new;
    }
    // Keep the old URLs alive.
    foreach ($old as $o) {
      if ($o === $new) {
        continue;
      }
      $source = ltrim($o, '/');
      $exists = $db->query('SELECT 1 FROM {redirect} WHERE redirect_source__path = :p', [':p' => $source])->fetchField();
      if ($exists) {
        continue;
      }
      Redirect::create([
        'redirect_source' => ['path' => $source, 'query' => []],
        'redirect_redirect' => ['uri' => 'internal:' . $new],
        'language' => 'und',
        'status_code' => 301,
      ])->save();
      $redirects++;
    }
  }
}
foreach ($samples as $s) {
  print "  $s\n";
}
printf(
  "%s %d profiles, %d already correct, %d failed, %d redirects created\n",
  $dry ? 'Would move' : 'Moved', $moved, $already, $failed, $redirects
);
