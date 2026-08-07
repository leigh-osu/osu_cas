<?php

/**
 * @file
 * Convert D7 right sidebars into an 8/4 body+sidebar layout section.
 *
 * D7 pages/books with field_right_sidebar content AND the "Display sidebar
 * content" flag (tid 375) rendered the sidebar in a col-sm-3 "well" beside
 * the content. The migration ignored the field. For each such node this
 * replaces the default body section (blb_col_1 with field_block:node:page:
 * body) with a 67/33 two-column section: the body copied into an inline
 * paragraph_block (editable in Layout Builder) plus the links/meta blocks
 * left, a new inline paragraph_block holding the sidebar HTML (legacy file
 * paths rewritten) right, boxed light-grey like D7's well.
 * Idempotent (skips nodes that already have a Right sidebar component);
 * runs at the end of rebuild_site.sh section 7.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/convert_right_sidebars.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Database\Database;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\osu_migrations_cas\Plugin\migrate\process\CasLarchInlineClasses;
use Drupal\osu_migrations_cas\Plugin\migrate\process\CasLegacyFilePaths;

$d7 = Database::getConnection('default', 'migrate');
$d10 = Database::getConnection();
$uuid = \Drupal::service('uuid');

$rows = $d7->query("
  SELECT fd.entity_id, fd.field_right_sidebar_value
  FROM field_data_field_right_sidebar fd
  JOIN field_data_field_sidebar_display sd
    ON sd.entity_id = fd.entity_id AND sd.field_sidebar_display_tid = 375
  WHERE fd.entity_type = 'node' AND fd.bundle IN ('page', 'book')
    AND LENGTH(TRIM(COALESCE(fd.field_right_sidebar_value, ''))) > 0")->fetchAllKeyed();
print count($rows) . " D7 sidebar nodes\n";

$node_map = [];
foreach (['cas_page_to_page', 'cas_book_to_page'] as $mig) {
  foreach ($d10->query("SELECT sourceid1, destid1 FROM migrate_map_$mig WHERE destid1 IS NOT NULL") as $r) {
    $node_map[$r->sourceid1] = $r->destid1;
  }
}

// 67/33 section settings, matching CasLayoutBase's blb_col_2 defaults but
// keeping the body section's plain 'container'.
$section_settings = [
  'breakpoints' => [
    'extra_wide_desktop' => 'blb_col_2_67_33',
    'desktop' => 'blb_col_2_67_33',
    'tablet' => 'blb_col_2_67_33',
    'mobile' => 'blb_col_1_full_width',
  ],
  'layout_regions_classes' => [
    'blb_region_col_1' => ['col-xxl-8', 'col-lg-8', 'col-md-8', 'col-12'],
    'blb_region_col_2' => ['col-xxl-4', 'col-lg-4', 'col-md-4', 'col-12'],
  ],
  'container' => 'container',
  'remove_gutters' => '0',
];

$converted = $skipped = 0;
foreach ($rows as $d7_nid => $html) {
  if (empty($node_map[$d7_nid])) {
    continue;
  }
  $node = Node::load($node_map[$d7_nid]);
  if (!$node || !$node->hasField('layout_builder__layout') || $node->get('layout_builder__layout')->isEmpty()) {
    print "SKIP $d7_nid: no layout\n";
    $skipped++;
    continue;
  }
  $layout = $node->get('layout_builder__layout');

  // Idempotency + find the body section.
  $body_delta = NULL;
  $already = FALSE;
  foreach ($layout->getSections() as $delta => $section) {
    foreach ($section->getComponents() as $component) {
      $cfg = $component->get('configuration');
      if (($cfg['label'] ?? '') === 'Right sidebar') {
        $already = TRUE;
        break 2;
      }
      if ($cfg['id'] === 'field_block:node:page:body' && $body_delta === NULL) {
        $body_delta = $delta;
      }
    }
  }
  if ($already) {
    // Converge earlier conversions that left the body as a field block:
    // swap it for an inline paragraph_block so it is editable in Layout
    // Builder.
    $changed = FALSE;
    foreach ($layout->getSections() as $section) {
      foreach ($section->getComponents() as $component) {
        $cfg = $component->get('configuration');
        if ($cfg['id'] !== 'field_block:node:page:body') {
          continue;
        }
        $body_item = $node->get('body');
        $body_block = BlockContent::create([
          'type' => 'paragraph_block',
          'info' => 'Body: ' . mb_substr($node->label(), 0, 100),
          'reusable' => 0,
          'body' => [
            'value' => $body_item->isEmpty() ? '' : $body_item->value,
            'format' => $body_item->isEmpty() ? 'full_html' : ($body_item->format ?? 'full_html'),
          ],
        ]);
        $body_block->save();
        $node->set('body', []);
        $component->setConfiguration([
          'id' => 'inline_block:paragraph_block',
          'label' => 'Body',
          'label_display' => '0',
          'provider' => 'layout_builder',
          'view_mode' => 'full',
          'block_revision_id' => $body_block->getRevisionId(),
          'block_serialized' => NULL,
          'context_mapping' => [],
        ]);
        $changed = TRUE;
      }
    }
    // Earlier runs may have swapped the block but left the field populated.
    if (!$changed && !$node->get('body')->isEmpty()) {
      foreach ($layout->getSections() as $section) {
        foreach ($section->getComponents() as $component) {
          if (($component->get('configuration')['label'] ?? '') === 'Body') {
            $node->set('body', []);
            $changed = TRUE;
            break 2;
          }
        }
      }
    }
    if ($changed) {
      $node->save();
      $converted++;
      print "FIXED $d7_nid -> {$node->id()} ({$node->label()}): body now inline/blanked\n";
    }
    continue;
  }
  if ($body_delta === NULL) {
    print "SKIP $d7_nid ({$node->label()}): no body section\n";
    $skipped++;
    continue;
  }

  $clean = CasLegacyFilePaths::rewriteText(CasLarchInlineClasses::mapText($html));
  $block = BlockContent::create([
    'type' => 'paragraph_block',
    'info' => 'Right sidebar: ' . mb_substr($node->label(), 0, 100),
    'reusable' => 0,
    'body' => ['value' => $clean, 'format' => 'full_html'],
  ]);
  $block->save();

  $sections = $layout->getSections();
  $old = $sections[$body_delta];
  $components = [];
  foreach ($old->getComponents() as $component) {
    $cfg = $component->get('configuration');
    if ($cfg['id'] === 'field_block:node:page:body') {
      // The body itself becomes an inline block so it is editable in
      // Layout Builder like the rest of the layout.
      $body_item = $node->get('body');
      $body_block = BlockContent::create([
        'type' => 'paragraph_block',
        'info' => 'Body: ' . mb_substr($node->label(), 0, 100),
        'reusable' => 0,
        'body' => [
          'value' => $body_item->isEmpty() ? '' : $body_item->value,
          'format' => $body_item->isEmpty() ? 'full_html' : ($body_item->format ?? 'full_html'),
        ],
      ]);
      $body_block->save();
      // The block is now authoritative; blank the node body field so the
      // edit form cannot mislead editors (Google CSE indexes rendered
      // output, so search is unaffected).
      $node->set('body', []);
      $moved = new SectionComponent($component->getUuid(), 'blb_region_col_1', [
        'id' => 'inline_block:paragraph_block',
        'label' => 'Body',
        'label_display' => '0',
        'provider' => 'layout_builder',
        'view_mode' => 'full',
        'block_revision_id' => $body_block->getRevisionId(),
        'block_serialized' => NULL,
        'context_mapping' => [],
      ]);
    }
    else {
      $moved = new SectionComponent($component->getUuid(), 'blb_region_col_1', $cfg, $component->toArray()['additional'] ?? []);
    }
    $moved->setWeight($component->getWeight());
    $components[$moved->getUuid()] = $moved;
  }
  $sidebar = new SectionComponent($uuid->generate(), 'blb_region_col_2', [
    'id' => 'inline_block:paragraph_block',
    'label' => 'Right sidebar',
    'label_display' => '0',
    'provider' => 'layout_builder',
    'view_mode' => 'full',
    'block_revision_id' => $block->getRevisionId(),
    'block_serialized' => NULL,
    'context_mapping' => [],
  ]);
  $sidebar->set('bootstrap_styles', [
    'block_style' => [
      'background' => ['background_type' => 'color'],
      'background_color' => ['class' => 'osu-bg-light-grey'],
      'padding' => ['class' => 'p-3'],
    ],
  ]);
  $components[$sidebar->getUuid()] = $sidebar;

  $layout->removeSection($body_delta);
  $layout->insertSection($body_delta, new Section('bootstrap_layout_builder:blb_col_2', $section_settings, $components));
  $node->save();
  $converted++;
  print "OK $d7_nid -> {$node->id()} ({$node->label()})\n";
}
print "Done: $converted converted, $skipped skipped.\n";
