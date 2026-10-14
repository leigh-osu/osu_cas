<?php

/**
 * @file
 * Converts the pasted Kaltura player iframes on the 2024 and 2025
 * Horticulture Seminar Series pages (nodes 263781, 272986) to kaltura media
 * embeds, matching how the earlier years' pages embed their recordings.
 *
 * Each iframe's entry_id is looked up as an existing kaltura media item
 * (https://media.oregonstate.edu/media/t/<entry_id>); one is created through
 * the oEmbed source when none exists, which also fills in its name and
 * thumbnail. The <p> wrapping a lone iframe is replaced along with it.
 *
 * Inline blocks are updated in place, without a new revision: the node's
 * layout references block_revision_id, so a new revision would orphan it.
 *
 *   drush --uri=https://horticulture.oregonstate.edu scr scripts-dev/hort_kaltura_iframes_to_media.php           # dry run
 *   drush --uri=https://horticulture.oregonstate.edu scr scripts-dev/hort_kaltura_iframes_to_media.php -- apply  # write
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

$apply = in_array('apply', $extra ?? [], TRUE);
$nids = [263781, 272986];
$db = \Drupal::database();

$find_media = function (string $entry) use ($db): ?Media {
  $mid = $db->query(
    "SELECT entity_id FROM {media__field_media_oembed_video} WHERE bundle = 'kaltura' AND field_media_oembed_video_value LIKE :u ORDER BY entity_id LIMIT 1",
    [':u' => '%/media/t/' . $db->escapeLike($entry)]
  )->fetchField();
  return $mid ? Media::load($mid) : NULL;
};

$created = $reused = $converted = 0;
foreach ($nids as $nid) {
  $node = Node::load($nid);
  foreach ($node->get('layout_builder__layout')->getSections() as $section) {
    foreach ($section->getComponents() as $component) {
      $revision_id = $component->get('configuration')['block_revision_id'] ?? NULL;
      if (!$revision_id) {
        continue;
      }
      $block = \Drupal::entityTypeManager()->getStorage('block_content')->loadRevision($revision_id);
      if (!$block->isDefaultRevision()) {
        throw new \RuntimeException("Block revision $revision_id on node $nid is not the default revision.");
      }
      $body = $block->get('body')->value;
      if (!preg_match_all('#(?:<p>\s*)?<iframe\b[^>]*\bentry_id=([0-9a-z_]+)[^>]*>.*?</iframe>(?:\s*</p>)?#s', $body, $matches, PREG_SET_ORDER)) {
        continue;
      }
      foreach ($matches as [$markup, $entry]) {
        $media = $find_media($entry);
        if ($media) {
          $reused++;
        }
        elseif ($apply) {
          $media = Media::create([
            'bundle' => 'kaltura',
            'field_media_oembed_video' => 'https://media.oregonstate.edu/media/t/' . $entry,
            'uid' => $node->getOwnerId(),
            'langcode' => 'en',
            'status' => 1,
          ]);
          $media->save();
          $created++;
        }
        else {
          $created++;
        }
        printf("  node %d block %d: %s -> %s\n", $nid, $block->id(), $entry,
          $media ? sprintf('media %d (%s)', $media->id(), mb_substr($media->getName(), 0, 70)) : 'new kaltura media');
        if ($media) {
          $embed = '<drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '" data-view-mode="default"></drupal-media>';
          $body = str_replace($markup, $embed, $body);
        }
        $converted++;
      }
      if ($apply) {
        $block->get('body')->value = $body;
        $block->setNewRevision(FALSE);
        $block->save();
      }
    }
  }
}
printf("%s %d iframes: %d existing media reused, %d %s.\n",
  $apply ? 'Converted' : 'Dry run: would convert', $converted, $reused, $created,
  $apply ? 'media created' : 'media to create');
