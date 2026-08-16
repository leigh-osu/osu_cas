<?php

/**
 * @file
 * Promote h4/h5 headings to h3 in blocks directly above people listings.
 *
 * D7 editors used h3 on some unit pages and h4/h5 on others for the heading
 * above each profiles listing. The listings are uniform now, so their
 * headings should be too — semantically, as real h3 elements. Walks every
 * layout that holds a cas_group_profiles component, finds the inline
 * paragraph block rendered directly above each listing, and rewrites
 * h4/h5 tags in its body to h3. Idempotent; runs after
 * place_profiles_group_membership.php in rebuilds.
 *
 * Usage: drush scr scripts-dev/fix_listing_heading_levels.php
 */

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$block_storage = \Drupal::entityTypeManager()->getStorage('block_content');

$nids = \Drupal::database()->query(
  "SELECT DISTINCT entity_id FROM {node__layout_builder__layout} WHERE layout_builder__layout_section LIKE '%cas_group_profiles%'"
)->fetchCol();

$fixed = $clean = 0;
foreach ($node_storage->loadMultiple($nids) as $node) {
  foreach ($node->get('layout_builder__layout')->getSections() as $section) {
    $components = $section->getComponents();
    uasort($components, fn($a, $b) => $a->getWeight() <=> $b->getWeight());
    $ordered = array_values($components);
    foreach ($ordered as $i => $component) {
      if (($component->get('configuration')['id'] ?? '') !== 'cas_group_profiles' || $i === 0) {
        continue;
      }
      $prev_cfg = $ordered[$i - 1]->get('configuration');
      if (!str_starts_with($prev_cfg['id'] ?? '', 'inline_block:') || empty($prev_cfg['block_revision_id'])) {
        continue;
      }
      $block = $block_storage->loadRevision($prev_cfg['block_revision_id']);
      if (!$block || !$block->hasField('body')) {
        continue;
      }
      $body = $block->get('body')->value ?? '';
      $new = preg_replace('/<(\/?)h[45]\b/i', '<${1}h3', $body);
      if ($new === $body) {
        $clean++;
        continue;
      }
      $block->set('body', ['value' => $new, 'format' => $block->get('body')->format]);
      $block->setSyncing(TRUE);
      $block->save();
      $fixed++;
    }
  }
}
printf("Promoted to h3: %d  Already h3/no heading: %d  (nodes scanned: %d)\n", $fixed, $clean, count($nids));
