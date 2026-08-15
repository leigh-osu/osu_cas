<?php

/**
 * @file
 * Backfill field_group_private from D7's og_access visibility.
 *
 * One-time repair for a database migrated before field_group_private and the
 * cas_node_group_private process line existed; rebuilds get the values from
 * the migration itself. Reads a newline-separated nid list (the effective
 * private set computed from D7: explicit group_content_access = 2, plus
 * default-visibility nodes whose every group is private), flags each node,
 * and saves it so its node_access records are reacquired.
 *
 * Usage:
 *   drush scr scripts-dev/backfill_group_private.php -- /path/to/nids.txt
 */

$list = $extra[0] ?? '/tmp/claude/d7_private_nids.txt';
if (!is_readable($list)) {
  fwrite(STDERR, "nid list not readable: $list\n");
  return;
}
$nids = array_filter(array_map('intval', file($list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)));
$storage = \Drupal::entityTypeManager()->getStorage('node');
$flagged = $skipped = $missing = 0;
foreach (array_chunk($nids, 50) as $chunk) {
  foreach ($storage->loadMultiple($chunk) as $node) {
    if (!$node->hasField('field_group_private')) {
      $skipped++;
      continue;
    }
    if (!empty($node->get('field_group_private')->value)) {
      continue;
    }
    $node->set('field_group_private', 1);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    $flagged++;
  }
  $storage->resetCache($chunk);
}
$missing = count($nids) - $flagged - $skipped;
printf("Flagged: %d  No field on bundle: %d  Already flagged or absent: %d\n", $flagged, $skipped, $missing);
