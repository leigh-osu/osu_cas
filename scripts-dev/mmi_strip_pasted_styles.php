<?php

/**
 * @file
 * MMI pages: remove pasted <style> blocks that recolour every link on the
 * page (the Office/Copilot paste boilerplate "a { color: #464feb }" plus
 * table borders) from inline blocks in MMI layouts. Other <style> tags are
 * left alone.
 *
 * Usage: drush scr scripts-dev/mmi_strip_pasted_styles.php [apply]
 */

$apply = in_array('apply', $extra ?? [], TRUE);
$db = \Drupal::database();
$etm = \Drupal::entityTypeManager();
$nids = $db->query("SELECT DISTINCT entity_id FROM {node__layout_builder__layout} WHERE entity_id >= 400000 AND entity_id < 500000")->fetchCol();
$n = 0;
foreach ($etm->getStorage('node')->loadMultiple($nids) as $node) {
  foreach ($node->get('layout_builder__layout')->getSections() as $section) {
    foreach ($section->getComponents() as $component) {
      $rid = $component->get('configuration')['block_revision_id'] ?? NULL;
      if (!$rid || !($block = $etm->getStorage('block_content')->loadRevision($rid)) || !$block->hasField('body')) {
        continue;
      }
      $body = (string) $block->get('body')->value;
      if (stripos($body, '#464feb') === FALSE) {
        continue;
      }
      $count = 0;
      $new = preg_replace('~<style\b[^>]*>(?:(?!</style>).)*#464feb(?:(?!</style>).)*</style>\s*~is', '', $body, -1, $count);
      if (!$count) {
        continue;
      }
      $n++;
      print "node {$node->id()} {$node->toUrl()->toString()} block {$block->id()} — $count style block(s)\n";
      if ($apply) {
        $block->get('body')->value = $new;
        $block->setNewRevision(FALSE);
        $block->save();
      }
    }
  }
}
print ($apply ? 'Cleaned' : 'Would clean') . " $n block(s)\n";
