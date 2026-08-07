<?php

/**
 * @file
 * Backfill page header banners from D7 field_picture.
 *
 * D7 rendered field_picture as a full-width header banner
 * (header_1200_x_400) above page/book content; the page migrations now map
 * it into field_page_banner_image, but the field only displays if the
 * node's Layout Builder override contains its field block. This script:
 *   1. populates field_page_banner_image from D7 where empty (covers DBs
 *      migrated before the mapping existed — high-water blocks re-import);
 *   2. prepends a banner section with the field block to each such node's
 *      layout when not already present.
 * Idempotent; runs at the end of rebuild_site.sh section 7.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/backfill_page_banner_images.php
 */

use Drupal\Core\Database\Database;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;

$d7 = Database::getConnection('default', 'migrate');
$d10 = Database::getConnection();
$uuid = \Drupal::service('uuid');

// D7 page/book nid -> picture fid.
$pictures = $d7->query("SELECT fd.entity_id, fd.field_picture_fid FROM field_data_field_picture fd WHERE fd.entity_type='node' AND fd.bundle IN ('page','book')")->fetchAllKeyed();

// D7 nid -> D10 nid via the two page migrations.
$node_map = [];
foreach (['cas_page_to_page', 'cas_book_to_page'] as $mig) {
  foreach ($d10->query("SELECT sourceid1, destid1 FROM migrate_map_$mig WHERE destid1 IS NOT NULL") as $r) {
    $node_map[$r->sourceid1] = $r->destid1;
  }
}

// D7 fid -> D10 media id (public first, then private).
$media_map = [];
foreach (['migrate_map_cas_media_private_images', 'migrate_map_upgrade_d7_media_images'] as $t) {
  foreach ($d10->query("SELECT sourceid1, destid1 FROM $t WHERE destid1 IS NOT NULL") as $r) {
    $media_map[$r->sourceid1] = $r->destid1;
  }
}

$field_set = $sections_added = $skipped = 0;
foreach ($pictures as $d7_nid => $fid) {
  if (empty($node_map[$d7_nid])) {
    continue;
  }
  $node = Node::load($node_map[$d7_nid]);
  if (!$node || !$node->hasField('field_page_banner_image')) {
    continue;
  }
  $changed = FALSE;
  if ($node->get('field_page_banner_image')->isEmpty()) {
    if (empty($media_map[$fid])) {
      print "WARN d7 $d7_nid: fid $fid has no migrated media\n";
      $skipped++;
      continue;
    }
    $node->set('field_page_banner_image', $media_map[$fid]);
    $changed = TRUE;
    $field_set++;
  }

  // Prepend the banner section unless the layout already has the block.
  if ($node->hasField('layout_builder__layout') && !$node->get('layout_builder__layout')->isEmpty()) {
    $layout = $node->get('layout_builder__layout');
    $present = FALSE;
    foreach ($layout->getSections() as $section) {
      foreach ($section->getComponents() as $component) {
        if ($component->getPluginId() === 'field_block:node:page:field_page_banner_image') {
          $present = TRUE;
          break 2;
        }
      }
    }
    if (!$present) {
      $component = new SectionComponent($uuid->generate(), 'blb_region_col_1', [
        'id' => 'field_block:node:page:field_page_banner_image',
        'label' => 'Banner image',
        'label_display' => '0',
        'provider' => 'layout_builder',
        'context_mapping' => [
          'entity' => 'layout_builder.entity',
          'view_mode' => 'view_mode',
        ],
        'formatter' => [
          'type' => 'entity_reference_entity_view',
          'label' => 'hidden',
          'settings' => ['view_mode' => 'page_banner'],
          'third_party_settings' => [],
        ],
      ]);
      $layout->insertSection(0, new Section('bootstrap_layout_builder:blb_col_1', ['container' => 'container'], [$component->getUuid() => $component]));
      $changed = TRUE;
      $sections_added++;
    }
  }

  if ($changed) {
    $node->save();
    print "OK d10 {$node->id()} ({$node->label()})\n";
  }
}
print "Done: field set on $field_set nodes, sections added on $sections_added, skipped $skipped.\n";
