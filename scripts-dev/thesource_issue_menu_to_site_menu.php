<?php

/**
 * @file
 * Turn a The Source issue menu built as a GROUP menu into a SITE menu.
 *
 * The group menu block in the header (group_content_menu:group) shows the
 * group's most recently created menu of that type, so an issue menu created
 * as a second group menu replaces The Source's main menu at the top of every
 * page. Earlier issues use a site menu (menu-<month>-<year>) placed on the
 * issue pages with system_menu_block, which never competes with the header.
 *
 * This script, for one issue:
 *  1. creates the site menu (system.menu.<menu_id>) if it does not exist,
 *  2. moves every link of the group menu into it (menu_name only; titles,
 *     targets, weights and ids are kept),
 *  3. on every node whose layout places group_content_menu:group, swaps that
 *     component for system_menu_block:<menu_id> in place (same uuid, region,
 *     weight and Bootstrap styles), adds cas-issue-menubar to the row that
 *     holds it, and saves a new revision,
 *  4. deletes the now-empty group menu.
 *
 * Dry run by default. Remote, from the host (eval'd from STDIN, so the
 * opening PHP tag is stripped):
 *   sed 1d scripts-dev/thesource_issue_menu_to_site_menu.php | vendor/bin/drush @osucas.prod -l https://agsci.oregonstate.edu scr -
 * To write, prepend the flag to the code — `scr - -- --apply` over the
 * Acquia alias fails with ssh exit 255 (2026-10-08):
 *   { echo '$extra[] = "--apply";'; sed 1d scripts-dev/thesource_issue_menu_to_site_menu.php; } | vendor/bin/drush @osucas.prod -l https://agsci.oregonstate.edu scr -
 * A new site menu means a new system_menu_block derivative: run `cr` on the
 * target afterwards or the block renders empty.
 * Locally:
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/thesource_issue_menu_to_site_menu.php -- --apply
 *
 * Afterwards export the new system.menu.<menu_id> into config/ — the repo
 * describes prod.
 */

use Drupal\system\Entity\Menu;

// The December 2026 issue (reported 2026-10-08).
$gid = 25338;
$group_menu_id = 196;
$expected_label = 'December 2026';
$menu_id = 'menu-december-2026';
$menu_description = 'December 2026 issue of The Source';
$menubar_class = 'cas-issue-menubar';

$apply = in_array('--apply', $extra ?? [], TRUE);
$etm = \Drupal::entityTypeManager();
$say = function (string $msg) { echo $msg, "\n"; };
$say($apply ? '*** APPLY ***' : '*** DRY RUN (pass -- --apply to write) ***');

// Preconditions: the group menu is the one we think it is, in this group.
$group = $etm->getStorage('group')->load($gid);
$group_menu = $etm->getStorage('group_content_menu')->load($group_menu_id);
if (!$group) {
  $say("ABORT: group $gid not found.");
  return;
}
if (!$group_menu) {
  // A rerun after the conversion: only the layout pass is left to do.
  if (!Menu::load($menu_id)) {
    $say("ABORT: neither group menu $group_menu_id nor site menu $menu_id exists.");
    return;
  }
  $say("Group menu $group_menu_id is already gone and $menu_id exists — layout pass only.");
}
elseif ($group_menu->label() !== $expected_label) {
  $say("ABORT: group menu $group_menu_id is '" . $group_menu->label() . "', expected '$expected_label'.");
  return;
}
$in_group = !$group_menu;
foreach ($group->getRelationships() as $relationship) {
  if (str_starts_with($relationship->getPluginId(), 'group_content_menu:') && (int) $relationship->getEntityId() === $group_menu_id) {
    $in_group = TRUE;
  }
}
if (!$in_group) {
  $say("ABORT: group menu $group_menu_id does not belong to group $gid (" . $group->label() . ').');
  return;
}
$say("Group: " . $group->label() . " ($gid); issue: $expected_label");

// 1. Site menu.
$menu = Menu::load($menu_id);
if ($menu) {
  $say("Site menu $menu_id already exists ('" . $menu->label() . "') — reusing it.");
}
else {
  $say("Create site menu $menu_id ('$expected_label').");
  if ($apply) {
    Menu::create([
      'id' => $menu_id,
      'label' => $expected_label,
      'description' => $menu_description,
      'langcode' => 'en',
    ])->save();
  }
}

// 2. Links.
$old_menu_name = 'group_menu_link_content-' . $group_menu_id;
$links = $etm->getStorage('menu_link_content')->loadByProperties(['menu_name' => $old_menu_name]);
uasort($links, fn($a, $b) => [$a->getWeight(), $a->label()] <=> [$b->getWeight(), $b->label()]);
$targets = [];
foreach ($links as $link) {
  $uri = $link->link->uri;
  if (preg_match('#^entity:node/(\d+)$#', $uri, $m)) {
    $targets[] = (int) $m[1];
  }
  $say(sprintf('Move link %d "%s" (%s, weight %d) -> %s', $link->id(), $link->label(), $uri, $link->getWeight(), $menu_id));
  if ($apply) {
    $link->set('menu_name', $menu_id)->save();
  }
}
$say(count($links) . ' link(s) to move.');
// Pages already in the site menu (a rerun) count as the issue's pages too.
foreach ($etm->getStorage('menu_link_content')->loadByProperties(['menu_name' => $menu_id]) as $link) {
  if (preg_match('#^entity:node/(\d+)$#', $link->link->uri, $m)) {
    $targets[] = (int) $m[1];
  }
}

// 3. Layouts. Every node that places the group menu block and belongs to
// this group — the issue pages, plus any other page in the group someone
// put the block on (reported, not skipped, so nothing is missed).
$nids = $etm->getStorage('node')->getQuery()->accessCheck(FALSE)
  ->exists('layout_builder__layout')->execute();
$group_nids = [];
foreach ($etm->getStorage('group_content')->loadByGroup($group) as $relationship) {
  if (str_starts_with($relationship->getPluginId(), 'group_node:')) {
    $group_nids[(int) $relationship->getEntityId()] = TRUE;
  }
}
$swapped = 0;
foreach (array_intersect_key(array_flip(array_map('intval', $nids)), $group_nids) as $nid => $_) {
  $node = $etm->getStorage('node')->load($nid);
  $changed = FALSE;
  foreach ($node->get('layout_builder__layout') as $item) {
    $section = $item->section;
    foreach ($section->getComponents() as $component) {
      $plugin_id = $component->getPluginId();
      if ($plugin_id !== 'group_content_menu:group' && $plugin_id !== 'system_menu_block:' . $menu_id) {
        continue;
      }
      // The row holding an issue menu carries cas-issue-menubar, as on every
      // earlier issue's pages (the menu block is alone in that row).
      $settings = $section->getLayoutSettings();
      $classes = preg_split('/\s+/', trim($settings['section_classes'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
      if (!in_array($menubar_class, $classes, TRUE)) {
        $classes[] = $menubar_class;
        $settings['section_classes'] = implode(' ', $classes);
        $section->setLayoutSettings($settings);
        $changed = TRUE;
      }
      if ($plugin_id !== 'group_content_menu:group') {
        continue;
      }
      $cfg = $component->get('configuration');
      $component->setConfiguration([
        'id' => 'system_menu_block:' . $menu_id,
        'label' => $expected_label,
        'provider' => 'system',
        'label_display' => '0',
        'level' => (int) ($cfg['level'] ?? 1),
        'depth' => (int) ($cfg['depth'] ?? 0),
        'expand_all_items' => (bool) ($cfg['expand_all_items'] ?? FALSE),
        'context_mapping' => [],
      ]);
      $changed = TRUE;
    }
  }
  if ($changed) {
    $swapped++;
    $note = in_array($nid, $targets, TRUE) ? '' : '  (NOT one of the menu\'s pages — check it)';
    $say("Issue menu block + $menubar_class row on node $nid \"" . $node->label() . "\"$note");
    if ($apply) {
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage("Issue menu: $expected_label site menu block in a $menubar_class row.");
      $node->setRevisionUserId(1);
      // A programmatic save keeps the previous revision's timestamp otherwise.
      $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
      $node->save();
    }
  }
}
$say("$swapped node(s) changed.");
foreach ($targets as $nid) {
  // A menu page without the block is fine, but worth knowing.
  if (!in_array($nid, array_keys($group_nids), TRUE)) {
    $say("Note: menu target node $nid is not in group $gid.");
  }
}

// 4. Group menu.
if (!$group_menu) {
  // Already deleted on an earlier run.
}
elseif ($apply) {
  $left = $etm->getStorage('menu_link_content')->loadByProperties(['menu_name' => $old_menu_name]);
  if ($left) {
    $say('NOT deleting group menu ' . $group_menu_id . ': ' . count($left) . ' link(s) still in it.');
  }
  else {
    $group_menu->delete();
    $say("Deleted group menu $group_menu_id; the header falls back to The Source's main group menu.");
  }
}
else {
  $say("Delete group menu $group_menu_id once its links are moved.");
}
