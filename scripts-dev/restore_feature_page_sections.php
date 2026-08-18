<?php

/**
 * @file
 * Restore the D7 feature-page field_sections content that no migration carried.
 *
 * D7's feature_page had a field_collection called field_sections holding extra
 * titled blocks under the body — an image, a caption, a chunk of HTML and
 * sometimes a YouTube video. Nothing maps it, so the text simply is not on the
 * D10 page. Twenty D7 nodes carry the field and 23 items exist, but most are
 * empty: only these seven pages lose anything real.
 *
 * Two nodes are deliberately not in the list. Node 27469 (Marine Studies
 * Initiative) holds lorem ipsum a developer left behind in 2016, and node
 * 83616 (Puerto Rico service learning) already has its section text in D10 —
 * it arrived by another route — so only its image is missing and appending the
 * block would duplicate the prose.
 *
 * Each item becomes one Layout Builder section holding a basic inline block,
 * appended after the body in D7's delta order, built like this:
 *
 *   image  -> <drupal-media> with the D7 alt/title, and the D7 caption as
 *             data-caption, which is exactly where D7 rendered it
 *   body   -> the section HTML verbatim; every source format (filtered_html,
 *             larch_html, full_html) already carries its own <p> tags and none
 *             of them ran an autop filter, so full_html is a safe destination
 *   video  -> <drupal-media> for the migrated remote_video
 *
 * Images and videos resolve through upgrade_d7_media_images and
 * upgrade_d7_media_remote_video; every one of the six was confirmed present
 * with a matching filename before this script was written.
 *
 * Idempotent: a node that already carries a section_restore component is left
 * alone.
 *
 * Usage: drush scr scripts-dev/restore_feature_page_sections.php -- --dry-run
 *        drush scr scripts-dev/restore_feature_page_sections.php
 */

use Drupal\Core\Database\Database;
use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

$dry = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$d7 = Database::getConnection('default', 'migrate');
$db = \Drupal::database();
$uuid_service = \Drupal::service('uuid');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

// The pages that lose real content. See the file docblock for the two D7 nodes
// with field_sections that are deliberately excluded.
$targets = [28596, 274691, 250691, 247656, 257326, 273726, 26513];

// Copied from a migrated blb_col_1 section (node 302219) rather than
// hand-written: bootstrap_styles is picky about shape and a string where it
// wants an array is fatal at render time, not at save time.
$bootstrap_styles = [
  'background' => ['background_type' => NULL],
  'background_color' => ['class' => NULL],
  'background_media' => [
    'image' => ['media_id' => NULL],
    'video' => ['media_id' => NULL],
    'background_options' => [
      'background_position' => 'center',
      'background_repeat' => 'no-repeat',
      'background_attachment' => 'not_fixed',
      'background_size' => 'cover',
    ],
  ],
  'text_color' => ['class' => NULL],
  'text_alignment' => ['class' => NULL],
  'padding' => ['class' => '_none'],
  'padding_left' => ['class' => '_none'],
  'padding_top' => ['class' => '_none'],
  'padding_right' => ['class' => '_none'],
  'padding_bottom' => ['class' => '_none'],
  'margin' => ['class' => '_none'],
  'margin_left' => ['class' => '_none'],
  'margin_top' => ['class' => 'mt-4'],
  'margin_right' => ['class' => '_none'],
  'margin_bottom' => ['class' => 'mb-4'],
  'border' => [
    'border_style' => ['class' => NULL],
    'border_width' => ['class' => '_none'],
    'border_color' => ['class' => NULL],
    'rounded_corners' => ['class' => '_none'],
    'border_left_style' => ['class' => NULL],
    'border_left_width' => ['class' => '_none'],
    'border_left_color' => ['class' => NULL],
    'border_top_style' => ['class' => NULL],
    'border_top_width' => ['class' => '_none'],
    'border_top_color' => ['class' => NULL],
    'border_right_style' => ['class' => NULL],
    'border_right_width' => ['class' => '_none'],
    'border_right_color' => ['class' => NULL],
    'border_bottom_style' => ['class' => NULL],
    'border_bottom_width' => ['class' => '_none'],
    'border_bottom_color' => ['class' => NULL],
    'rounded_corner_top_left' => ['class' => '_none'],
    'rounded_corner_top_right' => ['class' => '_none'],
    'rounded_corner_bottom_left' => ['class' => '_none'],
    'rounded_corner_bottom_right' => ['class' => '_none'],
  ],
  'box_shadow' => ['class' => NULL],
  'items_alignment' => ['class' => NULL],
  'scroll_effects' => ['class' => NULL],
  'min_height' => ['class' => 0],
];
$layout_settings = [
  'label' => '',
  'container_wrapper_classes' => '',
  'container_wrapper_attributes' => NULL,
  'container_wrapper' => ['bootstrap_styles' => $bootstrap_styles],
  'container_wrapper_bg_color_class' => '',
  'container_wrapper_bg_media' => NULL,
  'container' => 'container',
  'section_classes' => '',
  'section_attributes' => NULL,
  'regions_classes' => ['blb_region_col_1' => ''],
  'regions_attributes' => ['blb_region_col_1' => NULL],
  'breakpoints' => [
    'extra_wide_desktop' => 'blb_col_12',
    'desktop' => 'blb_col_12',
    'tablet' => 'blb_col_12',
    'mobile' => 'blb_col_12',
  ],
  'layout_regions_classes' => ['blb_region_col_1' => ['col-xxl-12', 'col-lg-12', 'col-md-12', 'col-12']],
  'remove_gutters' => '0',
  'context_mapping' => [],
];

/**
 * Resolves a D7 fid to a D10 media entity through the media migrations.
 */
$to_media = function (int $fid) use ($db, $media_storage): ?object {
  foreach (['migrate_map_upgrade_d7_media_images', 'migrate_map_upgrade_d7_media_remote_video',
    'migrate_map_upgrade_d7_media_local_video', 'migrate_map_upgrade_d7_media_documents',
    'migrate_map_upgrade_d7_media_kaltura', 'migrate_map_upgrade_d7_media_audio'] as $map) {
    if (!$db->schema()->tableExists($map)) {
      continue;
    }
    $mid = $db->query("SELECT destid1 FROM {" . $map . "} WHERE sourceid1 = :f", [':f' => $fid])->fetchField();
    if ($mid && ($media = $media_storage->load($mid))) {
      return $media;
    }
  }
  return NULL;
};

/**
 * Builds a <drupal-media> embed in the shape the rest of the site uses.
 */
$embed = function ($media, string $alt = '', string $title = '', string $caption = ''): string {
  $tag = '<drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '" data-view-mode="default"';
  foreach (['alt' => $alt, 'title' => $title, 'data-caption' => $caption] as $attr => $value) {
    $value = trim(strip_tags((string) $value));
    if ($value !== '') {
      $tag .= ' ' . $attr . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
    }
  }
  return $tag . '></drupal-media>';
};

$nodes_done = $blocks = $sections = $skipped = $missing_media = 0;
foreach ($targets as $nid) {
  $node = $node_storage->load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    printf("nid %d: no D10 node with a layout, skipped\n", $nid);
    continue;
  }
  $list = $node->get('layout_builder__layout');

  // Already restored?
  $already = FALSE;
  foreach ($list->getSections() as $section) {
    foreach ($section->toArray()['components'] as $component) {
      if (($component['configuration']['placement'] ?? '') === 'section_restore') {
        $already = TRUE;
      }
    }
  }
  if ($already) {
    printf("nid %-7s %-46s already restored\n", $nid, mb_substr($node->label(), 0, 46));
    $skipped++;
    continue;
  }

  $items = $d7->query("
    SELECT field_sections_value AS item_id, delta
    FROM {field_data_field_sections}
    WHERE entity_type = 'node' AND entity_id = :n AND deleted = 0
    ORDER BY delta", [':n' => $nid])->fetchAll();

  $added = 0;
  foreach ($items as $item) {
    $iid = $item->item_id;
    $content = (string) $d7->query("SELECT field_section_content_value FROM {field_data_field_section_content} WHERE entity_type = 'field_collection_item' AND entity_id = :i AND deleted = 0", [':i' => $iid])->fetchField();
    $caption = (string) $d7->query("SELECT field_image_caption_value FROM {field_data_field_image_caption} WHERE entity_type = 'field_collection_item' AND entity_id = :i AND deleted = 0", [':i' => $iid])->fetchField();
    $image = $d7->query("SELECT field_section_image_fid AS fid, field_section_image_alt AS alt, field_section_image_title AS title FROM {field_data_field_section_image} WHERE entity_type = 'field_collection_item' AND entity_id = :i AND deleted = 0", [':i' => $iid])->fetchObject();
    $video_fid = $d7->query("SELECT field_testingvid_fid FROM {field_data_field_testingvid} WHERE entity_type = 'field_collection_item' AND entity_id = :i AND deleted = 0", [':i' => $iid])->fetchField();

    $html = '';
    $has_caption_home = FALSE;
    if ($image) {
      $media = $to_media((int) $image->fid);
      if ($media) {
        $html .= $embed($media, (string) $image->alt, (string) $image->title, $caption) . "\n";
        $has_caption_home = TRUE;
      }
      else {
        $missing_media++;
        printf("  nid %d item %s: image fid %s has no D10 media\n", $nid, $iid, $image->fid);
      }
    }
    // A caption with no image to sit under still has to go somewhere.
    if ($caption !== '' && !$has_caption_home) {
      $html .= '<p><strong>' . htmlspecialchars(trim(strip_tags($caption)), ENT_QUOTES, 'UTF-8') . "</strong></p>\n";
    }
    $html .= trim($content);
    if ($video_fid) {
      $media = $to_media((int) $video_fid);
      if ($media) {
        $html .= "\n" . $embed($media);
      }
      else {
        $missing_media++;
        printf("  nid %d item %s: video fid %s has no D10 media\n", $nid, $iid, $video_fid);
      }
    }
    if (trim(strip_tags($html, '<drupal-media>')) === '') {
      // Nothing but whitespace — one of the empty D7 items.
      continue;
    }

    printf("  nid %-7s item %-7s delta %s  %5d chars%s%s\n", $nid, $iid, $item->delta, strlen(trim(strip_tags($content))),
      $image ? '  +image' : '', $video_fid ? '  +video' : '');
    $added++;
    $sections++;
    if ($dry) {
      continue;
    }

    $block = BlockContent::create([
      'type' => 'basic',
      'info' => 'In-line Block',
      'reusable' => FALSE,
      'body' => ['value' => $html, 'format' => 'full_html'],
    ]);
    $block->save();
    $blocks++;

    $section = new Section('bootstrap_layout_builder:blb_col_1', $layout_settings);
    $section->appendComponent(SectionComponent::fromArray([
      'uuid' => $uuid_service->generate(),
      'region' => 'blb_region_col_1',
      'configuration' => [
        'id' => 'inline_block:basic',
        'label' => 'In-line Block',
        'label_display' => 0,
        'provider' => 'layout_builder',
        'view_mode' => 'full',
        'block_id' => $block->id(),
        'block_revision_id' => $block->getRevisionId(),
        'block_serialized' => NULL,
        'context_mapping' => [],
        // Marker so a re-run leaves this node alone.
        'placement' => 'section_restore',
      ],
      'additional' => [],
      'weight' => 0,
    ]));
    $list->appendSection($section);
  }

  if (!$added) {
    printf("nid %-7s %-46s nothing to restore\n", $nid, mb_substr($node->label(), 0, 46));
    continue;
  }
  $nodes_done++;
  if (!$dry) {
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
  }
}

printf(
  "\n%s %d sections onto %d pages (%d already restored, %d media references unresolved)\n",
  $dry ? 'Would add' : 'Added', $sections, $nodes_done, $skipped, $missing_media
);
if (!$dry && $blocks) {
  \Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list', 'block_content_list']);
}
