<?php

/**
 * @file
 * Restore the D7 attachment lists that pages and book pages lost.
 *
 * D7's field_file_attachment rendered a download list under the body. Articles
 * kept theirs — the migration maps them into field_story_media — but `page` has
 * no document field, so nothing carried the ones on page and book nodes.
 *
 * The audit counted 132 files on 56 nodes. That is the size of the field, not
 * the size of the loss: field_file_attachment has display_field enabled, and
 * 84 of the 132 rows are flagged display = 0, which D7 honoured. Rendering D7
 * node 216506 confirms it — six files attached, the three flagged rows absent
 * from the page. Restoring all 132 would publish 84 files D7 deliberately
 * hid, so this restores the 48 that were actually visible, on 29 nodes.
 *
 * The links go into the node body rather than a new document field: every one
 * of the 29 nodes places its body block in its layout, and a direct href is
 * how the rest of the site links documents (985 block bodies do it; embedding
 * a document as <drupal-media> appears nowhere).
 *
 * Files resolve to their migrated media, then to the file's real D10 URI, so
 * both schemes come out right — public files under /sites/…/files/… and the 32
 * private ones under /system/files/…, all of which were confirmed downloadable
 * anonymously before this was written.
 *
 * Idempotent: a body that already carries the cas-attachments block is left
 * alone, and a file already linked somewhere in the body is not repeated.
 *
 * Usage: drush scr scripts-dev/restore_page_attachments.php -- --dry-run
 *        drush scr scripts-dev/restore_page_attachments.php
 */

use Drupal\Core\Database\Database;

$dry = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$d7 = Database::getConnection('default', 'migrate');
$db = \Drupal::database();
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
$file_storage = \Drupal::entityTypeManager()->getStorage('file');
$url_generator = \Drupal::service('file_url_generator');

// D7 nid -> D10 nid, for both content types that fed `page`.
$node_map = [];
foreach (['migrate_map_cas_page_to_page', 'migrate_map_cas_book_to_page'] as $map) {
  $node_map += $db->query("SELECT sourceid1, destid1 FROM {" . $map . "} WHERE destid1 IS NOT NULL")->fetchAllKeyed();
}

$media_maps = array_values(array_filter([
  'migrate_map_upgrade_d7_media_documents',
  'migrate_map_upgrade_d7_media_images',
  'migrate_map_cas_media_private_documents',
  'migrate_map_cas_media_private_images',
  'migrate_map_upgrade_d7_media_local_video',
  'migrate_map_upgrade_d7_media_audio',
], fn($m) => $db->schema()->tableExists($m)));

/**
 * Resolves a D7 fid to the URL of the migrated file.
 */
$to_url = function (int $fid) use ($db, $media_maps, $media_storage, $file_storage, $url_generator): ?string {
  foreach ($media_maps as $map) {
    $mid = $db->query("SELECT destid1 FROM {" . $map . "} WHERE sourceid1 = :f", [':f' => $fid])->fetchField();
    if (!$mid || !($media = $media_storage->load($mid))) {
      continue;
    }
    $target = $media->getSource()->getSourceFieldValue($media);
    $file = $target ? $file_storage->load($target) : NULL;
    if ($file) {
      return $url_generator->generateString($file->getFileUri());
    }
  }
  return NULL;
};

// Only the rows D7 actually displayed.
$rows = $d7->query("
  SELECT a.entity_id AS nid, a.delta, a.field_file_attachment_fid AS fid, f.filename
  FROM {field_data_field_file_attachment} a
  JOIN {file_managed} f ON f.fid = a.field_file_attachment_fid
  WHERE a.entity_type = 'node'
    AND a.bundle IN ('page', 'book')
    AND a.deleted = 0
    AND a.field_file_attachment_display = 1
  ORDER BY a.entity_id, a.delta")->fetchAll();

$hidden = (int) $d7->query("
  SELECT COUNT(*) FROM {field_data_field_file_attachment}
  WHERE entity_type = 'node' AND bundle IN ('page', 'book') AND deleted = 0
    AND field_file_attachment_display = 0")->fetchField();
printf("D7 attachments on page/book: %d displayed, %d flagged hidden and left alone\n", count($rows), $hidden);

$by_node = [];
foreach ($rows as $row) {
  $nid = $node_map[$row->nid] ?? NULL;
  if (!$nid) {
    printf("  D7 nid %s has no D10 node, skipped\n", $row->nid);
    continue;
  }
  $by_node[$nid][] = $row;
}

$updated = $skipped = $already_linked = $unresolved = $links = 0;
foreach ($by_node as $nid => $attachments) {
  $node = $node_storage->load($nid);
  if (!$node || !$node->hasField('body')) {
    printf("  nid %s: no node or no body field\n", $nid);
    continue;
  }
  $body = (string) $node->get('body')->value;
  if (str_contains($body, 'cas-attachments')) {
    $skipped++;
    continue;
  }

  $items = [];
  foreach ($attachments as $a) {
    $url = $to_url((int) $a->fid);
    if (!$url) {
      $unresolved++;
      printf("  nid %s: fid %s (%s) has no migrated media\n", $nid, $a->fid, $a->filename);
      continue;
    }
    // Do not repeat a link the body already carries.
    if (str_contains($body, $url)) {
      $already_linked++;
      continue;
    }
    $items[] = '<li><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
      . htmlspecialchars($a->filename, ENT_QUOTES, 'UTF-8') . '</a></li>';
  }
  if (!$items) {
    continue;
  }

  $heading = count($items) === 1 ? 'Attachment' : 'Attachments';
  $html = "\n<div class=\"cas-attachments\">\n<h2>" . $heading . "</h2>\n<ul>\n"
    . implode("\n", $items) . "\n</ul>\n</div>\n";

  printf("  nid %-7s %-46s +%d\n", $nid, mb_substr((string) $node->label(), 0, 46), count($items));
  $links += count($items);
  $updated++;
  if ($dry) {
    continue;
  }
  $node->set('body', [
    'value' => rtrim($body) . $html,
    'summary' => $node->get('body')->summary,
    'format' => $node->get('body')->format ?: 'full_html',
  ]);
  $node->setNewRevision(FALSE);
  $node->setSyncing(TRUE);
  $node->save();
}

printf(
  "\n%s %d links onto %d pages (%d already carried the list, %d links already in the body, %d unresolved)\n",
  $dry ? 'Would add' : 'Added', $links, $updated, $skipped, $already_linked, $unresolved
);
if (!$dry && $updated) {
  \Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list']);
}
