<?php

/**
 * Rewrite links to the retired centerforsmallfarms.oregonstate.edu hostname.
 *
 * The domain record centerforsmallfarms_oregonstate_edu was renamed to
 * hostname crafs.oregonstate.edu; the old hostname no longer negotiates
 * (401 at the Acquia edge), so every absolute URL against it is publicly
 * broken. Verified 2026-09-02: all referenced paths serve on crafs —
 * two old paths 301 to /centerforresilient/organic-agriculture-program,
 * which we write directly.
 *
 * Touches: node bodies (8), Layout Builder inline block bodies (8),
 * one menu link. Saves in place (no new revisions): LB components pin
 * block_revision_id, so a new block revision would strand the layout on
 * the old text.
 *
 * Idempotent: rows without the old hostname are untouched; re-runs no-op.
 *
 *   drush scr scripts-dev/fix_centerforsmallfarms_links.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;

$db = \Drupal::database();
$old = 'centerforsmallfarms.oregonstate.edu';

$rewrite = function (string $text) use ($old): string {
  // Canonicalise the two paths that 301 on crafs, then swap the hostname
  // (upgrading any http:// scheme) and drop analytics junk on the root URL.
  $text = preg_replace(
    '~https?://' . preg_quote($old, '~') . '/centerforsmallfarms/(?:programs/)?organic-agriculture-program~',
    'https://crafs.oregonstate.edu/centerforresilient/organic-agriculture-program',
    $text);
  $text = preg_replace(
    '~https?://' . preg_quote($old, '~') . '/\?_ga[^"\'<\s]*~',
    'https://crafs.oregonstate.edu/',
    $text);
  return preg_replace('~https?://' . preg_quote($old, '~') . '~', 'https://crafs.oregonstate.edu', $text);
};

// --- node bodies ---------------------------------------------------------
$nids = $db->query('SELECT DISTINCT entity_id FROM {node__body} WHERE body_value LIKE :s OR body_summary LIKE :s',
  [':s' => "%$old%"])->fetchCol();
foreach ($nids as $nid) {
  $node = Node::load($nid);
  $body = $node->get('body')->first()->getValue();
  $before = $body['value'] . ($body['summary'] ?? '');
  $body['value'] = $rewrite($body['value']);
  if (!empty($body['summary'])) {
    $body['summary'] = $rewrite($body['summary']);
  }
  if ($before === $body['value'] . ($body['summary'] ?? '')) {
    printf("node %d: no change\n", $nid);
    continue;
  }
  $node->get('body')->first()->setValue($body);
  $node->setNewRevision(FALSE);
  $node->setSyncing(TRUE);
  $node->save();
  printf("node %d fixed: %s\n", $nid, $node->getTitle());
}

// --- inline block bodies -------------------------------------------------
$bids = $db->query('SELECT DISTINCT entity_id FROM {block_content__body} WHERE body_value LIKE :s',
  [':s' => "%$old%"])->fetchCol();
foreach ($bids as $bid) {
  $block = BlockContent::load($bid);
  $value = $block->get('body')->value;
  $new = $rewrite($value);
  if ($new === $value) {
    printf("block %d: no change\n", $bid);
    continue;
  }
  $block->get('body')->value = $new;
  $block->setNewRevision(FALSE);
  $block->setSyncing(TRUE);
  $block->save();
  printf("block %d fixed: %s\n", $bid, $block->label());
}

// --- menu link -----------------------------------------------------------
$mids = $db->query('SELECT DISTINCT id FROM {menu_link_content_data} WHERE link__uri LIKE :s',
  [':s' => "%$old%"])->fetchCol();
foreach ($mids as $mid) {
  $link = MenuLinkContent::load($mid);
  $uri = $link->get('link')->uri;
  $new = $rewrite($uri);
  if ($new === $uri) {
    printf("menu link %d: no change\n", $mid);
    continue;
  }
  $link->get('link')->uri = $new;
  $link->setSyncing(TRUE);
  $link->save();
  printf("menu link %d fixed: %s -> %s\n", $mid, $link->getTitle(), $new);
}

// --- verify --------------------------------------------------------------
$left = 0;
foreach (['node__body' => 'body_value', 'node__body ' => 'body_summary',
          'block_content__body' => 'body_value',
          'menu_link_content_data' => 'link__uri'] as $table => $col) {
  $left += (int) $db->query('SELECT COUNT(*) FROM {' . trim($table) . '} WHERE ' . $col . ' LIKE :s',
    [':s' => "%$old%"])->fetchField();
}
printf("remaining references to %s: %d (expect 0)\n", $old, $left);
