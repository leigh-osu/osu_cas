<?php

/**
 * @file
 * Strips the empty paragraphs and headings padding the 2023, 2024 and 2025
 * Horticulture Seminar Series pages (nodes 288356, 263781, 272986).
 *
 * The D7 two-column paragraphs carried runs of hundreds of <p>&nbsp;</p>
 * (spacing to line the columns up around the old players); in D10 they only
 * show as long blank stretches. An element is empty when it holds nothing
 * but whitespace, &nbsp; and <br>. Media embeds opening or closing a
 * paragraph are moved out of it first, since a rendered embed splits its
 * <p> and leaves an empty one behind.
 *
 * Inline blocks are updated in place, without a new revision: the node's
 * layout references block_revision_id, so a new revision would orphan it.
 *
 *   drush --uri=https://horticulture.oregonstate.edu scr scripts-dev/hort_seminar_strip_empty_paragraphs.php           # dry run
 *   drush --uri=https://horticulture.oregonstate.edu scr scripts-dev/hort_seminar_strip_empty_paragraphs.php -- apply  # write
 */

use Drupal\node\Entity\Node;

$apply = in_array('apply', $extra ?? [], TRUE);
$nids = [288356, 263781, 272986];
$empty = '#<(p|h[1-6])\b[^>]*>(?:\s|&nbsp;|&\#160;|\xc2\xa0|<br\s*/?>)*</\1>#iu';
$blank = '(?:\s|&nbsp;|&\#160;|\xc2\xa0|<br\s*/?>)*';
// An embed opening a paragraph, and an embed closing one.
$lead = '#(<p\b[^>]*>)' . $blank . '(<drupal-media\b[^>]*></drupal-media>)' . $blank . '#iu';
$trail = '#' . $blank . '(<drupal-media\b[^>]*></drupal-media>)' . $blank . '</p>#iu';

$total = 0;
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
      // Move media embeds out of the paragraphs wrapping them: a rendered
      // embed is a block element, so a wrapping <p> splits around it and
      // leaves an empty paragraph behind. Text sharing the paragraph stays.
      $stripped = $body;
      $unwrapped = 0;
      do {
        $stripped = preg_replace($lead, "\$2\n\n\$1", $stripped, -1, $leading);
        $stripped = preg_replace($trail, "</p>\n\n\$1", $stripped, -1, $trailing);
        $unwrapped += $leading + $trailing;
      } while ($leading + $trailing);
      $stripped = preg_replace($empty, '', $stripped, -1, $count);
      if (!$count && !$unwrapped) {
        continue;
      }
      // Collapse the blank lines left behind.
      $stripped = trim(preg_replace("/(\r?\n[\t ]*){3,}/", "\n\n", $stripped));
      printf("  node %d block %d: %d embeds unwrapped, %d empty elements removed, %d -> %d chars\n", $nid, $block->id(), $unwrapped, $count, strlen($body), strlen($stripped));
      $total += $count;
      $total_unwrapped = ($total_unwrapped ?? 0) + $unwrapped;
      if ($apply) {
        $block->get('body')->value = $stripped;
        $block->setNewRevision(FALSE);
        $block->save();
      }
    }
  }
}
printf("%s %d embeds and %s %d empty elements.\n",
  $apply ? 'Unwrapped' : 'Dry run: would unwrap', $total_unwrapped ?? 0,
  $apply ? 'removed' : 'remove', $total);
