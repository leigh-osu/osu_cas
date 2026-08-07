<?php

/**
 * @file
 * Prune empty migrated paragraph blocks from Layout Builder layouts.
 *
 * Empty D7 paragraphs migrated into empty inline paragraph_blocks that
 * render as invisible padded boxes (phantom spacing). Remove a component
 * when its block has no body and no background purpose:
 * - block body empty and field_eb_background_fc empty, AND
 * - the component's bootstrap_styles carry no background colour/media and
 *   no min-height (colored columns, background-image columns and spacer
 *   bands all keep their components).
 * A section left with no components is removed too, unless its own
 * settings carry a background/min-height (divider bands are
 * component-less by design). Idempotent; runs in rebuild section 7.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/prune_empty_layout_blocks.php
 */

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;

$db = Database::getConnection();
$storage = \Drupal::entityTypeManager()->getStorage('block_content');

$nids = $db->query("SELECT DISTINCT entity_id FROM node__layout_builder__layout WHERE layout_builder__layout_section LIKE '%inline_block:paragraph_block%'")->fetchCol();
print count($nids) . " candidate nodes\n";

$removed_components = $removed_sections = $touched_nodes = 0;
foreach ($nids as $nid) {
  $node = Node::load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    continue;
  }
  $layout = $node->get('layout_builder__layout');
  $changed = FALSE;

  foreach ($layout->getSections() as $section) {
    foreach ($section->getComponents() as $component) {
      if ($component->getPluginId() !== 'inline_block:paragraph_block') {
        continue;
      }
      $styles = $component->get('bootstrap_styles')['block_style'] ?? [];
      if (!empty($styles['background_color']['class'])
        || !empty($styles['background_media']['image']['media_id'])
        || !empty($styles['background_media']['video']['media_id'])
        || !empty($styles['min_height']['class'])) {
        continue;
      }
      $attrs = $component->get('component_attributes')['block_content_attributes']['class'] ?? '';
      if ($attrs !== '') {
        continue;
      }
      $revision_id = $component->get('configuration')['block_revision_id'] ?? NULL;
      if (!$revision_id) {
        continue;
      }
      $block = $storage->loadRevision($revision_id);
      if (!$block) {
        continue;
      }
      $body_empty = $block->get('body')->isEmpty() || trim(strip_tags((string) $block->get('body')->value, '<img><iframe><drupal-media><video><audio><embed><object><hr>')) === '';
      if (!$body_empty || !$block->get('field_eb_background_fc')->isEmpty()) {
        continue;
      }
      $section->removeComponent($component->getUuid());
      $removed_components++;
      $changed = TRUE;
    }
  }

  // Drop sections the pruning emptied, unless they carry their own styling
  // (divider bands) — walk by delta from the end so removals are stable.
  $sections = $layout->getSections();
  for ($delta = count($sections) - 1; $delta >= 0; $delta--) {
    $section = $layout->getSections()[$delta] ?? NULL;
    if (!$section || count($section->getComponents()) > 0) {
      continue;
    }
    $bs = $section->getLayoutSettings()['container_wrapper']['bootstrap_styles'] ?? [];
    if (!empty($bs['background_color']['class'])
      || !empty($bs['background_media'])
      || !empty($bs['min_height']['class'])) {
      continue;
    }
    if ($changed) {
      $layout->removeSection($delta);
      $removed_sections++;
    }
  }

  if ($changed) {
    $node->save();
    $touched_nodes++;
    if ($touched_nodes <= 15 || $touched_nodes % 50 === 0) {
      print "OK $nid ({$node->label()})\n";
    }
  }
}
print "Done: $removed_components empty blocks removed, $removed_sections emptied sections dropped, $touched_nodes nodes updated.\n";
