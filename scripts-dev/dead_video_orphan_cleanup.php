<?php

/**
 * Delete orphaned dead-video media and their orphan host blocks.
 *
 * Four remote-video media entities (dead on YouTube / media.oregonstate.edu,
 * erroring in the prod log on every cron thumbnail refresh) are embedded only
 * in non-reusable migrated-paragraph blocks that no node layout, revision
 * layout, or inline-block usage row references. Nothing renders them; delete
 * both halves.
 *
 * Every orphan claim is re-verified here at run time — a pair that gained a
 * reference since the audit is skipped, not deleted.
 *
 * Dry run (default):
 *   drush scr scripts-dev/dead_video_orphan_cleanup.php
 * Delete:
 *   drush scr scripts-dev/dead_video_orphan_cleanup.php -- delete
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;

// [block_content id, media id, dead video id] from the 2026-09-01 audit.
$pairs = [
  [30546, 49547, '1_2sjk5kcy'],
  [32472, 49503, '1_9muahgy6'],
  [17396, 49991, 'Jr4Sa7FzUAA'],
  [2245, 49841, 'ebblfH3Qeeg'],
];

$apply = in_array('delete', $extra ?? [], TRUE);
print $apply ? "APPLY mode: verified orphans will be deleted.\n" : "DRY RUN: no deletions (pass '-- delete' to apply).\n";

$db = \Drupal::database();
$field_manager = \Drupal::service('entity_field.manager');

/**
 * Every current-revision reference to a UUID or media id outside the pair.
 */
$references = function (string $uuid, int $mid, int $own_bid) use ($db, $field_manager): array {
  $found = [];

  // Embeds by UUID in node and block body text (any block but its own host).
  foreach (['node__body' => 'entity_id', 'block_content__body' => 'entity_id'] as $table => $col) {
    $ids = $db->query("SELECT $col FROM {" . $table . "} WHERE body_value LIKE :u", [':u' => '%' . $uuid . '%'])->fetchCol();
    foreach ($ids as $id) {
      if ($table === 'block_content__body' && (int) $id === $own_bid) {
        continue;
      }
      $found[] = "$table:$id";
    }
  }

  // Layout Builder sections (current and revision) mentioning the UUID.
  foreach (['node__layout_builder__layout', 'node_revision__layout_builder__layout'] as $table) {
    $ids = $db->query("SELECT DISTINCT entity_id FROM {" . $table . "} WHERE layout_builder__layout_section LIKE :u", [':u' => '%' . $uuid . '%'])->fetchCol();
    foreach ($ids as $id) {
      $found[] = "$table:$id";
    }
  }

  // Entity-reference fields targeting media, on any fieldable entity type.
  foreach ($field_manager->getFieldMapByFieldType('entity_reference') as $etype => $fields) {
    foreach ($fields as $fname => $info) {
      $storage = FieldStorageConfig::loadByName($etype, $fname);
      if (!$storage || $storage->getSetting('target_type') !== 'media') {
        continue;
      }
      $table = $etype . '__' . $fname;
      if (!$db->schema()->tableExists($table)) {
        continue;
      }
      $ids = $db->query("SELECT entity_id FROM {" . $table . "} WHERE {$fname}_target_id = :m", [':m' => $mid])->fetchCol();
      foreach ($ids as $id) {
        $found[] = "$table:$id";
      }
    }
  }

  return $found;
};

foreach ($pairs as [$bid, $mid, $video_id]) {
  print "== $video_id  (block $bid, media $mid)\n";

  $block = BlockContent::load($bid);
  $media = Media::load($mid);
  if (!$block && !$media) {
    print "   both already gone, nothing to do\n";
    continue;
  }

  // The block must still be the orphan the audit saw.
  if ($block) {
    if ($block->isReusable()) {
      print "   SKIP: block $bid is reusable now\n";
      continue;
    }
    $usage = $db->query("SELECT COUNT(*) FROM {inline_block_usage} WHERE block_content_id = :b", [':b' => $bid])->fetchField();
    $block_refs = $references($block->uuid(), 0, $bid);
    if ($usage || $block_refs) {
      print "   SKIP: block $bid is referenced (" . ($usage ? "inline_block_usage" : implode(', ', $block_refs)) . ")\n";
      continue;
    }
  }

  // The media must be embedded nowhere but that block.
  if ($media) {
    $expected_url = strpos($video_id, '1_') === 0
      ? "https://media.oregonstate.edu/media/t/$video_id"
      : "https://www.youtube.com/watch?v=$video_id";
    $actual_url = trim((string) $media->get('field_media_oembed_video')->value);
    if ($actual_url !== $expected_url) {
      print "   SKIP: media $mid URL is \"$actual_url\", expected \"$expected_url\"\n";
      continue;
    }
    $media_refs = $references($media->uuid(), $mid, $bid);
    if ($media_refs) {
      print "   SKIP: media $mid is referenced elsewhere: " . implode(', ', $media_refs) . "\n";
      continue;
    }
  }

  if (!$apply) {
    print "   verified orphan — would delete " . ($media ? "media $mid" : '') . ($media && $block ? ' + ' : '') . ($block ? "block $bid" : '') . "\n";
    continue;
  }

  if ($media) {
    $media->delete();
    print "   deleted media $mid\n";
  }
  if ($block) {
    $block->delete();
    print "   deleted block $bid\n";
  }
}

print "Done.\n";
