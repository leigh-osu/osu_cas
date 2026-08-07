<?php

/**
 * @file
 * Post-rebuild fix: remove D7 spacer.gif hack sections, replace with padding.
 *
 * D7 editors created thin spacer rows purely for vertical spacing — a 1x1
 * transparent spacer.gif in its own row, or an entirely empty
 * adjustable-columns row. Migrated, they render wrong: the gif's paragraph
 * wrapper inflates the band (~100px), an empty block collapses to 0.
 *
 * Affected layouts (D7 content is frozen, so the nid list is fixed; block
 * ids are NOT stable across rebuilds and are discovered, not hardcoded):
 * - node 5344 (/home/alumni): 30px black bands bracketing the magazine
 *   section -> sections removed, magazine section gets pt-4-5/pb-4-5.
 * - node 285676 (/mycas/...CARE...): 16px transparent gaps between content
 *   sections -> sections removed, following section gets pt-3 (1rem).
 * Orphaned spacer-gif blocks are deleted afterwards. Idempotent.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/remove_spacer_blocks.php
 */

use Drupal\node\Entity\Node;

$block_storage = \Drupal::entityTypeManager()->getStorage('block_content');

/**
 * TRUE when a body value is nothing but a spacer.gif media embed.
 */
function _is_spacer_body(?string $body): bool {
  if ($body === NULL || stripos($body, 'drupal-media') === FALSE) {
    return FALSE;
  }
  $uuids = [];
  preg_match_all('~<drupal-media[^>]*data-entity-uuid="([^"]+)"~', $body, $m);
  foreach ($m[1] as $uuid) {
    $media = \Drupal::service('entity.repository')->loadEntityByUuid('media', $uuid);
    if (!$media || stripos($media->label(), 'spacer') === FALSE) {
      return FALSE;
    }
    $uuids[] = $uuid;
  }
  if (!$uuids) {
    return FALSE;
  }
  $rest = trim(strip_tags(preg_replace('~<drupal-media[^>]*>\s*</drupal-media>~', '', $body)));
  return $rest === '';
}

/**
 * TRUE when the component's block is a D7 spacer: body that is only a
 * spacer.gif embed, or a null/empty body (the empty adjustable-columns
 * row hack; real content blocks always carry text or media).
 */
function _is_spacer_component($component, $block_storage, array &$spacer_block_ids): bool {
  $cfg = $component->get('configuration');
  if (!isset($cfg['block_revision_id']) || !str_starts_with($cfg['id'] ?? '', 'inline_block:')) {
    return FALSE;
  }
  $block = $block_storage->loadRevision($cfg['block_revision_id']);
  if (!$block || !$block->hasField('body')) {
    return FALSE;
  }
  $body = $block->get('body')->value;
  $empty = trim(strip_tags((string) $body, '<img><iframe><drupal-media><hr>')) === '';
  if (_is_spacer_body($body) || ($body === NULL || $empty) && !str_contains((string) $body, 'drupal-media')) {
    $spacer_block_ids[$block->id()] = TRUE;
    return TRUE;
  }
  return FALSE;
}

/**
 * Removes spacer-only sections from a node's layout and adds $padding to
 * the section that follows each removed one, preferring the neighbor whose
 * background matches the spacer's (a black gap must stay black).
 */
function _strip_spacer_sections(int $nid, array $padding, $block_storage, array &$deleted): void {
  $node = Node::load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    print "node $nid: not found\n";
    return;
  }
  $layout = $node->get('layout_builder__layout');
  $sections = $layout->getSections();
  $remove = [];
  $spacer_block_ids = [];
  foreach ($sections as $delta => $section) {
    $components = $section->getComponents();
    if (!$components) {
      continue;
    }
    $all_spacers = TRUE;
    foreach ($components as $component) {
      if (!_is_spacer_component($component, $block_storage, $spacer_block_ids)) {
        $all_spacers = FALSE;
        break;
      }
    }
    if ($all_spacers) {
      $remove[] = $delta;
    }
  }
  if (!$remove) {
    print "node $nid: no spacer sections found\n";
    return;
  }
  $bg_of = function ($section): string {
    $bs = $section->getLayoutSettings()['container_wrapper']['bootstrap_styles'] ?? [];
    return $bs['background_color']['class'] ?? '';
  };
  $pad_targets = [];
  foreach ($remove as $delta) {
    $next = NULL;
    for ($d = $delta + 1; $d < count($sections); $d++) {
      if (!in_array($d, $remove, TRUE)) {
        $next = $d;
        break;
      }
    }
    $prev = NULL;
    for ($d = $delta - 1; $d >= 0; $d--) {
      if (!in_array($d, $remove, TRUE)) {
        $prev = $d;
        break;
      }
    }
    $spacer_bg = $bg_of($sections[$delta]);
    $neighbor = NULL;
    foreach ([$next, $prev] as $cand) {
      if ($cand !== NULL && $bg_of($sections[$cand]) === $spacer_bg) {
        $neighbor = $cand;
        break;
      }
    }
    $neighbor ??= $next ?? $prev;
    if ($neighbor !== NULL) {
      $pad_targets[$neighbor][] = $delta < $neighbor ? 'top' : 'bottom';
    }
  }
  foreach ($pad_targets as $delta => $sides) {
    $section = $sections[$delta];
    $settings = $section->getLayoutSettings();
    $bs = &$settings['container_wrapper']['bootstrap_styles'];
    // The bootstrap_styles Padding plugin only runs when the 'padding' key
    // itself is present in storage; directional keys alone never render.
    $bs += ['padding' => ['class' => '_none']];
    foreach (array_unique($sides) as $side) {
      $key = $side === 'top' ? 'padding_top' : 'padding_bottom';
      $bs[$key] = ['class' => $padding[$side]];
    }
    unset($bs);
    $section->setLayoutSettings($settings);
    print "node $nid: section $delta padded (" . implode('+', array_unique($sides)) . ")\n";
  }
  foreach (array_reverse($remove) as $delta) {
    $layout->removeSection($delta);
    print "node $nid: removed spacer section $delta\n";
  }
  $node->save();
  foreach (array_keys($spacer_block_ids) as $bid) {
    $block = $block_storage->load($bid);
    if ($block) {
      $block->delete();
      $deleted[] = $bid;
    }
  }
}

$deleted = [];

// /home/alumni: 30px black bands -> pt/pb-4-5 (2rem) on the magazine section.
_strip_spacer_sections(5344, ['top' => 'pt-4-5', 'bottom' => 'pb-4-5'], $block_storage, $deleted);

// CARE page: 16px transparent gaps -> pt-3 (1rem) on each following section.
_strip_spacer_sections(285676, ['top' => 'pt-3', 'bottom' => 'pb-3'], $block_storage, $deleted);

// Delete orphaned spacer-gif-only blocks (D7 had one never placed anywhere).
$db = \Drupal::database();
$ids = $db->query("SELECT entity_id FROM block_content__body WHERE body_value LIKE '%spacer%'")->fetchCol();
foreach ($ids as $bid) {
  $block = $block_storage->load($bid);
  if ($block && _is_spacer_body($block->get('body')->value)) {
    $usage = $db->query("SELECT COUNT(*) FROM node__layout_builder__layout WHERE layout_builder__layout_section LIKE :p", [':p' => '%"block_uuid";s:36:"' . $block->uuid() . '"%'])->fetchField();
    $revs = $db->query("SELECT revision_id FROM block_content_revision WHERE id = :id", [':id' => $bid])->fetchCol();
    foreach ($revs as $rev) {
      $usage += (int) $db->query("SELECT COUNT(*) FROM node__layout_builder__layout WHERE layout_builder__layout_section LIKE :p", [':p' => '%block_revision_id";i:' . $rev . ';%'])->fetchField();
    }
    if ((int) $usage === 0) {
      $block->delete();
      $deleted[] = $bid;
    }
  }
}
print "Done: deleted blocks " . (implode(', ', $deleted) ?: '(none)') . "\n";
