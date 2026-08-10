<?php

/**
 * @file
 * Reports migrated picbox cards whose link will have no accessible name.
 *
 * A picbox card is a link. Screen readers name it from, in order: the
 * component title ("Display title", set from the D7 headline by
 * CasParagraphsLayout::setPicboxComponentTitle()), the link label, or — for
 * an untitled card, where the template keeps the image inside the anchor —
 * the image's alt text. A card with a link and none of the three is an
 * unnamed link: WCAG 2.4.4 / 4.1.2, and not something the migration can fix,
 * because there is no text anywhere to use.
 *
 * Cards that merely lack an alt are NOT listed: their headline names the
 * link, and the restructured template keeps the image out of the anchor
 * precisely so a descriptive alt stays an image description.
 *
 * Usage:
 *   ddev drush --uri=https://osu-cas.ddev.site scr \
 *     drush/scripts/picbox_unnamed_links_report.php
 *
 * Writes scripts-dev/picbox_unnamed_links.csv (untracked).
 */

use Drupal\Core\Database\Database;

$out = DRUPAL_ROOT . '/../scripts-dev/picbox_unnamed_links.csv';

$d7 = Database::getConnection('default', 'migrate');
$db = \Drupal::database();
$bcs = \Drupal::entityTypeManager()->getStorage('block_content');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$id_map = \Drupal::service('plugin.manager.migration')
  ->createInstance('field_collection_field_lp_picbox__to__layout_builder')
  ->getIdMap();

// Every picbox card that is a link but carries no link label.
$candidates = $db->query(
  "SELECT l.entity_id AS bid, l.field_osu_card_link_uri AS uri
   FROM {block_content__field_osu_card_link} l
   JOIN {block_content} b ON b.id = l.entity_id AND b.type = 'osu_card'
   WHERE l.field_osu_card_link_uri <> ''
     AND (l.field_osu_card_link_title IS NULL OR TRIM(l.field_osu_card_link_title) = '')"
)->fetchAllKeyed();
print 'linked picbox cards with no link label: ' . count($candidates) . PHP_EOL;

// Index block revision -> node, so each finding can name the page to fix.
$hosts = [];
$nids = $db->query(
  'SELECT entity_id FROM {node__layout_builder__layout} WHERE layout_builder__layout_section LIKE :p',
  [':p' => '%picbox%']
)->fetchCol();
foreach ($nids as $nid) {
  $node = $node_storage->load($nid);
  if (!$node) {
    continue;
  }
  foreach ($node->get('layout_builder__layout') as $item) {
    foreach ($item->section->getComponents() as $component) {
      $rev = $component->get('configuration')['block_revision_id'] ?? NULL;
      if ($rev) {
        $hosts[$rev][] = $nid;
      }
    }
  }
}
print 'nodes scanned for picbox components: ' . count($nids) . PHP_EOL;

$rows = [];
$no_headline_no_alt = 0;
foreach ($candidates as $bid => $uri) {
  $block = $bcs->load($bid);
  if (!$block) {
    continue;
  }

  // Will the migration give this card a component title? Only if the D7
  // picbox had a headline.
  $source = $id_map->lookupSourceId(['id' => $bid]);
  $headline = '';
  if (!empty($source['item_id'])) {
    $headline = trim((string) $d7->query(
      'SELECT field_lp_picbox_box_headline_value FROM {field_data_field_lp_picbox_box_headline}
       WHERE entity_type = :e AND entity_id = :i',
      [':e' => 'field_collection_item', ':i' => $source['item_id']]
    )->fetchField());
  }
  if ($headline !== '') {
    continue;
  }

  // No headline: the image alt is the only remaining accessible name.
  $alt = '';
  $media_id = '';
  if (!$block->get('field_osu_card_image')->isEmpty()) {
    $media = $block->get('field_osu_card_image')->entity;
    if ($media) {
      $media_id = $media->id();
      if ($media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
        $alt = trim((string) $media->get('field_media_image')->first()->alt);
      }
    }
  }
  if ($alt !== '') {
    continue;
  }
  $no_headline_no_alt++;

  $rev = $bcs->getLatestRevisionId($bid);
  $node_ids = array_unique($hosts[$rev] ?? []);
  foreach ($node_ids ?: [NULL] as $nid) {
    $node = $nid ? $node_storage->load($nid) : NULL;
    $rows[] = [
      $bid,
      $media_id ?: '(no image)',
      $uri,
      $nid ?: '(not placed)',
      $node ? $node->label() : '',
      $node ? $node->toUrl()->toString() : '',
      $source['item_id'] ?? '',
    ];
  }
}

$fh = fopen($out, 'w');
fputcsv($fh, ['block_id', 'media_id', 'link_uri', 'nid', 'node_title', 'node_url', 'd7_item_id']);
foreach ($rows as $row) {
  fputcsv($fh, $row);
}
fclose($fh);

print 'unnamed links (no headline, no link label, no alt): ' . $no_headline_no_alt . PHP_EOL;
print 'rows written (one per hosting page): ' . count($rows) . PHP_EOL;
print 'report: ' . realpath($out) . PHP_EOL;
