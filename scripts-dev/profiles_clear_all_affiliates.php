<?php
/**
 * Profiles: clear "Send to all affiliates" (field_domain_all_affiliates).
 *
 * Usage: drush scr profiles_clear_all_affiliates.php            (dry run)
 *        drush scr profiles_clear_all_affiliates.php -- --apply (write)
 *
 * Saves the default revision in place (no new revision, changed date kept).
 * Skips profiles with no Domain access so they don't drop off every domain.
 * Reports profiles with a newer draft revision, which keeps its own value.
 */
$apply = in_array('--apply', $extra ?? [], TRUE);
$db = \Drupal::database();
$q = "SELECT a.entity_id FROM {node__field_domain_all_affiliates} a JOIN {node_field_data} n ON n.nid = a.entity_id WHERE n.type = 'osu_profile' AND a.deleted = 0 AND a.field_domain_all_affiliates_value = 1 ORDER BY a.entity_id";
$nids = $db->query($q)->fetchCol();
echo count($nids), " profiles set to all affiliates\n";
$storage = \Drupal::entityTypeManager()->getStorage('node');
$done = 0;
$skipped = 0;
$drafts = [];
foreach (array_chunk($nids, 50) as $chunk) {
  foreach ($storage->loadMultiple($chunk) as $node) {
    if ($node->get('field_domain_access')->isEmpty()) {
      $skipped++;
      continue;
    }
    if ($storage->getLatestRevisionId($node->id()) != $node->getRevisionId()) {
      $drafts[] = $node->id();
    }
    if ($apply) {
      $node->set('field_domain_all_affiliates', 0);
      $node->setNewRevision(FALSE);
      $node->setSyncing(TRUE);
      $node->save();
    }
    $done++;
  }
  $storage->resetCache($chunk);
}
echo ($apply ? 'updated ' : 'would update '), "$done, skipped $skipped (no domain access)\n";
echo 'with a newer draft revision: ', count($drafts), $drafts ? ' -> ' . implode(', ', $drafts) : '', "\n";
echo 'still set to all affiliates: ', $db->query("SELECT COUNT(*) FROM ($q) x")->fetchField(), "\n";
