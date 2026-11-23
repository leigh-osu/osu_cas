<?php

/**
 * @file
 * MMI pages: swap the agsci group-landing story cards for the plain news
 * teaser list (D7 mmi_news blocks 1/2 were an unformatted list).
 *
 * Usage: drush scr scripts-dev/mmi_news_blocks_to_list.php [apply]
 */

$apply = in_array('apply', $extra ?? [], TRUE);
$from = 'views_block:osu_stories__groups-group_landing_page_stories_block';
$to = 'views_block:news_items_by_group-teasers';
$db = \Drupal::database();
$nids = $db->query("SELECT DISTINCT entity_id FROM {node__layout_builder__layout} WHERE entity_id >= 400000 AND entity_id < 500000 AND layout_builder__layout_section LIKE :l", [':l' => '%group_landing_page_stories_block%'])->fetchCol();
$storage = \Drupal::entityTypeManager()->getStorage('node');
$n = 0;
foreach ($nids as $nid) {
  $node = $storage->load($nid);
  $changed = 0;
  foreach ($node->get('layout_builder__layout')->getSections() as $section) {
    foreach ($section->getComponents() as $component) {
      $config = $component->get('configuration');
      if (($config['id'] ?? '') === $from) {
        $config['id'] = $to;
        $component->setConfiguration($config);
        $changed++;
      }
    }
  }
  if ($changed) {
    $n++;
    print "$nid {$node->toUrl()->toString()} — $changed block(s)\n";
    if ($apply) {
      $node->setNewRevision(FALSE);
      $node->setSyncing(TRUE);
      $node->save();
    }
  }
}
print ($apply ? 'Updated' : 'Would update') . " $n node(s)\n";
