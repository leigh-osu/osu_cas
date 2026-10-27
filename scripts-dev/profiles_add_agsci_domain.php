<?php
// Published profiles with no "Domain access" entry get agsci added.
$db = \Drupal::database();
$nids = $db->query("SELECT n.nid FROM {node_field_data} n LEFT JOIN {node__field_domain_access} d ON d.entity_id = n.nid AND d.deleted = 0 WHERE n.type = 'osu_profile' AND n.status = 1 AND d.entity_id IS NULL ORDER BY n.nid")->fetchCol();
echo count($nids), " profiles to update\n";
$storage = \Drupal::entityTypeManager()->getStorage('node');
$done = 0;
foreach ($nids as $nid) {
  $node = $storage->load($nid);
  if (!$node || !$node->get('field_domain_access')->isEmpty()) {
    continue;
  }
  $node->set('field_domain_access', [['target_id' => 'agsci_oregonstate_edu']]);
  $node->save();
  $done++;
}
echo "updated $done\n";
echo "still without domain access: ", $db->query("SELECT COUNT(*) FROM {node_field_data} n LEFT JOIN {node__field_domain_access} d ON d.entity_id = n.nid AND d.deleted = 0 WHERE n.type = 'osu_profile' AND n.status = 1 AND d.entity_id IS NULL")->fetchField(), "\n";
