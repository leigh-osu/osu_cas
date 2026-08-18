<?php

/**
 * @file
 * Make the header navigation follow the domain, not an arbitrary group.
 *
 * The header carried two blocks: a site-wide "main" menu of five links, and
 * the group menu. Neither behaved.
 *
 * The group menu block took its group from Group's own context, and Group's
 * fallback (GroupRouteContextTrait::getBestCandidate) scans the route's
 * entities for anything belonging to a group when the route names none. On a
 * user page that resolves to the account's *first membership* — so someone in
 * four groups saw one of them at random in the header, usually pointing at a
 * different domain than the page they were reading.
 *
 * The five-link main menu was the only thing on pages with no group at all,
 * and it is not real navigation.
 *
 * So: the group menu block now reads osu_cas_multisite_groups' own context,
 * which resolves the group a page genuinely belongs to and otherwise falls
 * back to the current domain's default group (the group owning that domain's
 * front page — see CurrentGroup::getGroupForDisplay). Every one of the 35
 * domains resolves that way. The main menu block is switched off, since every
 * page now has a real menu.
 *
 * Requires osu_cas_multisite_groups to provide the context service, so it runs
 * after that module is enabled. Idempotent.
 *
 * Usage: drush scr scripts-dev/configure_group_menus.php
 */

use Drupal\block\Entity\Block;

$context = '@osu_cas_multisite_groups.group_display_context:group';
$changed = 0;

// Point the group menu blocks at the domain-aware context.
foreach (['manzanita_groupmenu', 'madrone_groupmenu'] as $id) {
  $block = Block::load($id);
  if (!$block) {
    printf("  %-26s not present\n", $id);
    continue;
  }
  $settings = $block->get('settings');
  $before = $settings['context_mapping']['group'] ?? '(none)';
  $dirty = FALSE;
  if ($before !== $context) {
    $settings['context_mapping']['group'] = $context;
    $block->set('settings', $settings);
    $dirty = TRUE;
  }
  // The block carried a block_visibility_group condition with an empty value,
  // which restricts nothing. Drop it so the stored state is honest.
  $visibility = $block->getVisibility();
  if (isset($visibility['condition_group']) && empty($visibility['condition_group']['block_visibility_group'])) {
    $block->setVisibilityConfig('condition_group', []);
    $dirty = TRUE;
  }
  if ($dirty) {
    $block->save();
    $changed++;
    printf("  %-26s context: %s -> %s\n", $id, $before, $context);
  }
  else {
    printf("  %-26s already correct\n", $id);
  }
}

// Retire the five-link main menu on the front-facing themes. stable9 is a base
// theme with no pages of its own, so it is left alone.
foreach (['manzanita_main_menu', 'madrone_main_menu'] as $id) {
  $block = Block::load($id);
  if (!$block) {
    printf("  %-26s not present\n", $id);
    continue;
  }
  if ($block->status()) {
    $block->disable()->save();
    $changed++;
    printf("  %-26s disabled (superseded by the group menu)\n", $id);
  }
  else {
    printf("  %-26s already disabled\n", $id);
  }
}

printf("%d block(s) changed\n", $changed);
