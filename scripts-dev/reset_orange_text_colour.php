<?php

/**
 * Reset the Beaver Orange text colour in Layout Builder layouts.
 *
 * Orange is reserved for links; the "osu-text-osuorange" option was removed
 * from the Bootstrap Styles text-colour list (bootstrap_styles.settings) on
 * 2026-09-02. Layouts that already carried the class keep rendering orange
 * text until they are re-saved, so this sets every such text colour — on
 * section wrappers and on block styles — to "_none" (the plugin's no-colour
 * value) in place, without new revisions. Orange backgrounds are untouched.
 *
 * Idempotent: a second run finds nothing.
 *
 *   drush scr scripts-dev/reset_orange_text_colour.php              (dry run)
 *   drush scr scripts-dev/reset_orange_text_colour.php -- --apply
 */

use Drupal\node\Entity\Node;

$apply = in_array('--apply', $extra ?? [], TRUE);
$mode = $apply ? 'APPLY' : 'DRY RUN';
print "== $mode ==\n";

$class = 'osu-text-osuorange';
$db = \Drupal::database();
$nids = $db->query("SELECT DISTINCT entity_id FROM {node__layout_builder__layout} WHERE layout_builder__layout_section LIKE :p", [':p' => "%$class%"])->fetchCol();
printf("%d node layout(s) carry %s\n", count($nids), $class);

$total = 0;
foreach (Node::loadMultiple($nids) as $node) {
  $changed = 0;
  foreach ($node->get('layout_builder__layout')->getSections() as $i => $section) {
    $settings = $section->getLayoutSettings();
    if (($settings['container_wrapper']['bootstrap_styles']['text_color']['class'] ?? NULL) === $class) {
      $settings['container_wrapper']['bootstrap_styles']['text_color']['class'] = '_none';
      $section->setLayoutSettings($settings);
      $changed++;
      printf("  node %d section %d: section text colour reset\n", $node->id(), $i);
    }
    foreach ($section->getComponents() as $uuid => $component) {
      $styles = $component->get('bootstrap_styles');
      if (($styles['block_style']['text_color']['class'] ?? NULL) === $class) {
        $styles['block_style']['text_color']['class'] = '_none';
        $component->set('bootstrap_styles', $styles);
        $changed++;
        printf("  node %d section %d block %s: block text colour reset\n", $node->id(), $i, substr($uuid, 0, 8));
      }
    }
  }
  if ($changed && $apply) {
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
  }
  if ($changed) {
    printf("node %d (%s): %d reset%s\n", $node->id(), $node->getTitle(), $changed, $apply ? ', saved' : '');
  }
  $total += $changed;
}

$left = $db->query("SELECT COUNT(*) FROM {node__layout_builder__layout} WHERE layout_builder__layout_section LIKE :p", [':p' => "%$class%"])->fetchField();
printf("== done (%s): %d text colour(s) %s; %d layout row(s) still carry the class\n", $mode, $total, $apply ? 'reset' : 'would be reset', $left);
