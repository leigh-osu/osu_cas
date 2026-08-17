<?php

/**
 * @file
 * Give feed nodes a single URL: /feed/<title>.
 *
 * The group_content pathauto pattern used to alias group-placed content as
 * /group/<gid>/<title>, so every feed carried two aliases and Drupal picked
 * whichever was created last. With that pattern gone this keeps the /feed/
 * alias, drops the group-shaped one, and leaves a redirect so existing links
 * still resolve. Feeds with no /feed/ alias get one generated from the default
 * pattern. Idempotent.
 *
 * Usage: drush scr scripts-dev/repath_feed_aliases.php -- --dry-run
 *        drush scr scripts-dev/repath_feed_aliases.php
 */
use Drupal\redirect\Entity\Redirect;

$dry = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$db = \Drupal::database();
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$generator = \Drupal::service('pathauto.generator');

$nids = $db->query("SELECT nid FROM {node_field_data} WHERE type = 'feed'")->fetchCol();
$dropped = $redirects = $generated = 0;
foreach ($node_storage->loadMultiple($nids) as $node) {
  $system = '/node/' . $node->id();
  $aliases = array_values($alias_storage->loadByProperties(['path' => $system]));
  $keep = NULL;
  foreach ($aliases as $a) {
    if (str_starts_with($a->getAlias(), '/feed/')) {
      $keep = $a;
    }
  }
  if (!$keep) {
    // No /feed/ alias yet — let pathauto build one from the default pattern.
    if (!$dry) {
      $result = $generator->updateEntityAlias($node, 'bulkupdate', ['force' => TRUE]);
      if ($result) {
        $generated++;
      }
    }
    else {
      $generated++;
    }
    $aliases = $dry ? $aliases : array_values($alias_storage->loadByProperties(['path' => $system]));
    foreach ($aliases as $a) {
      if (str_starts_with($a->getAlias(), '/feed/')) {
        $keep = $a;
      }
    }
  }
  foreach ($aliases as $a) {
    if ($keep && $a->id() === $keep->id()) {
      continue;
    }
    $old = $a->getAlias();
    $new = $keep ? $keep->getAlias() : NULL;
    printf("  %-46s -> %s\n", $old, $new ?: '(no /feed alias)');
    if ($dry || !$new) {
      $dropped++;
      continue;
    }
    $a->delete();
    $dropped++;
    $source = ltrim($old, '/');
    if (!$db->query('SELECT 1 FROM {redirect} WHERE redirect_source__path = :p', [':p' => $source])->fetchField()) {
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
printf("%s stale aliases dropped: %d, redirects created: %d, aliases generated: %d\n",
  $dry ? '[dry run]' : 'Done —', $dropped, $redirects, $generated);
