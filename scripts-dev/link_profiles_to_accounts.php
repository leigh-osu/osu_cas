<?php

/**
 * @file
 * Connect profiles to their user accounts and retire the dead person URLs.
 *
 * Three related things, all keyed off migrate_map_upgrade_d7_user_to_profile
 * (uid -> profile nid, 2,217 rows, verified name-for-name):
 *
 * 1. Fill field_profile_user on every profile whose account still exists, so a
 *    person can be given edit access to their own profile. The field was
 *    defined but never populated by the migration.
 *
 * 2. Retire /people/<name>. Those aliased /user/N account pages, which are 403
 *    for the public, and only covered 447 of 839 accounts. /user/<uid> is
 *    enough for an account, so the pathauto pattern goes and each alias
 *    becomes a redirect to the person's public profile.
 *
 * 3. Retire /users/<name> — 3,700 D7-era aliases pointing at /user/N, of which
 *    2,237 point at accounts that were never migrated. They shadow the 1,801
 *    redirects that already exist, which is why they 404 rather than
 *    redirecting; deleting the alias lets the redirect win, and any name
 *    without one gets a redirect created.
 *
 * Everything lands on /directory/people/<name>. Idempotent.
 *
 * Usage: drush scr scripts-dev/link_profiles_to_accounts.php -- --dry-run
 *        drush scr scripts-dev/link_profiles_to_accounts.php
 */

use Drupal\redirect\Entity\Redirect;

$dry = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$db = \Drupal::database();
$alias_manager = \Drupal::service('path_alias.manager');
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');

// uid -> profile nid.
$map = $db->query('SELECT sourceid1, destid1 FROM {migrate_map_upgrade_d7_user_to_profile} WHERE destid1 IS NOT NULL')->fetchAllKeyed();
printf("uid -> profile map: %d entries\n", count($map));

// ---------------------------------------------------------------- 1. link
$live = $db->query('SELECT uid FROM {users_field_data} WHERE uid > 0')->fetchCol();
$live = array_flip($live);
$to_link = [];
foreach ($map as $uid => $nid) {
  if (isset($live[$uid])) {
    $to_link[$nid] = (int) $uid;
  }
}
printf("profiles whose account still exists: %d\n", count($to_link));

$linked = $already = 0;
foreach (array_chunk(array_keys($to_link), 200, TRUE) as $chunk) {
  foreach ($node_storage->loadMultiple($chunk) as $node) {
    $want = $to_link[$node->id()];
    $have = $node->get('field_profile_user')->target_id ?? NULL;
    if ((int) $have === $want) {
      $already++;
      continue;
    }
    $linked++;
    if (!$dry) {
      $node->set('field_profile_user', ['target_id' => $want]);
      $node->setNewRevision(FALSE);
      $node->setSyncing(TRUE);
      $node->save();
    }
  }
}
printf("%s field_profile_user on %d profiles (%d already correct)\n", $dry ? 'Would set' : 'Set', $linked, $already);

// ------------------------------------------------- 2/3. retire the aliases
// Drop the pathauto pattern first so nothing regenerates /people/<name>.
$pattern = \Drupal::entityTypeManager()->getStorage('pathauto_pattern')->load('pattern_for_user_account_page_paths');
if ($pattern) {
  printf("%s pathauto pattern %s (%s)\n", $dry ? 'Would delete' : 'Deleting', $pattern->id(), $pattern->getPattern());
  if (!$dry) {
    $pattern->delete();
  }
}

$profile_alias = [];
$dropped = $redirected = $kept = $nomatch = 0;
$samples = [];
foreach (['/people/', '/users/'] as $prefix) {
  $rows = $db->query('SELECT id, alias, path FROM {path_alias} WHERE alias LIKE :p', [':p' => $prefix . '%'])->fetchAll();
  foreach ($rows as $row) {
    if (!preg_match('~^/user/(\d+)$~', $row->path, $m)) {
      // Not an account alias — leave it alone.
      $kept++;
      continue;
    }
    $uid = (int) $m[1];
    $nid = $map[$uid] ?? NULL;
    if (!$nid) {
      $nomatch++;
      continue;
    }
    if (!isset($profile_alias[$nid])) {
      $profile_alias[$nid] = $alias_manager->getAliasByPath('/node/' . $nid);
    }
    $target = $profile_alias[$nid];
    if ($target === '/node/' . $nid) {
      // Profile has no alias (the 8 with empty titles) — leave the old alias.
      $nomatch++;
      continue;
    }
    if (count($samples) < 6) {
      $samples[] = sprintf('%-34s -> %s', $row->alias, $target);
    }
    $dropped++;
    if ($dry) {
      continue;
    }
    $alias_storage->load($row->id)?->delete();
    $source = ltrim($row->alias, '/');
    if (!$db->query('SELECT 1 FROM {redirect} WHERE redirect_source__path = :p', [':p' => $source])->fetchField()) {
      Redirect::create([
        'redirect_source' => ['path' => $source, 'query' => []],
        'redirect_redirect' => ['uri' => 'internal:' . $target],
        'language' => 'und',
        'status_code' => 301,
      ])->save();
      $redirected++;
    }
  }
}
foreach ($samples as $s) {
  print "  $s\n";
}
printf(
  "%s %d account aliases, %d redirects created, %d left (no profile or no profile alias), %d non-account aliases untouched\n",
  $dry ? 'Would remove' : 'Removed', $dropped, $redirected, $nomatch, $kept
);
