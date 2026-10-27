<?php
/**
 * Unpublished profiles with no "Domain access" entry get agsci added.
 *
 * Usage: drush scr profiles_add_agsci_domain_unpublished.php            (dry run)
 *        drush scr profiles_add_agsci_domain_unpublished.php -- --apply (write)
 *
 * Saves the default revision in place (no new revision). Leaves
 * field_domain_all_affiliates alone. Reports any profile that also has a
 * newer draft revision, since that draft would still lack the domain.
 */
$apply = in_array('--apply', $extra ?? [], TRUE);
$db = \Drupal::database();
$missing = "SELECT n.nid FROM {node_field_data} n LEFT JOIN {node__field_domain_access} d ON d.entity_id = n.nid AND d.deleted = 0 WHERE n.type = 'osu_profile' AND n.status = 0 AND d.entity_id IS NULL ORDER BY n.nid";
$nids = $db->query($missing)->fetchCol();
echo count($nids), " unpublished profiles without domain access\n";
$storage = \Drupal::entityTypeManager()->getStorage('node');
$done = 0;
$drafts = [];
foreach (array_chunk($nids, 50) as $chunk) {
  foreach ($storage->loadMultiple($chunk) as $node) {
    if (!$node->get('field_domain_access')->isEmpty()) {
      continue;
    }
    $latest = $storage->getLatestRevisionId($node->id());
    if ($latest != $node->getRevisionId()) {
      $drafts[] = $node->id() . " (default {$node->getRevisionId()}, latest $latest)";
    }
    if ($apply) {
      $node->set('field_domain_access', [['target_id' => 'agsci_oregonstate_edu']]);
      $node->setNewRevision(FALSE);
      $node->setSyncing(TRUE);
      $node->save();
    }
    $done++;
  }
  $storage->resetCache($chunk);
}
echo ($apply ? 'updated ' : 'would update '), $done, "\n";
echo 'with a newer draft revision: ', count($drafts), $drafts ? ' -> ' . implode(', ', $drafts) : '', "\n";
echo 'still without domain access: ', $db->query("SELECT COUNT(*) FROM ($missing) x")->fetchField(), "\n";
