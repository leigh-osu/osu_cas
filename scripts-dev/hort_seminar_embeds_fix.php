<?php

/**
 * @file
 * Repoints the 23 dead video embeds on horticulture node 288356
 * (2023 Horticulture Seminar Series) at their Kaltura media.
 *
 * Every <drupal-media> on the page carried a UUID that no media entity ever
 * had; the recordings themselves migrated fine (upgrade_d7_media_kaltura,
 * mids 49574-49597). Each embed follows its talk's text, which was matched
 * to the media names by date and speaker (2026-10-14).
 *
 * The inline blocks are updated in place, without a new revision: the node's
 * layout references block_revision_id, so a new revision would orphan it.
 *
 *   drush --uri=https://horticulture.oregonstate.edu scr scripts-dev/hort_seminar_embeds_fix.php           # dry run
 *   drush --uri=https://horticulture.oregonstate.edu scr scripts-dev/hort_seminar_embeds_fix.php -- apply  # write
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\media\Entity\Media;

$apply = in_array('apply', $extra ?? [], TRUE);

// Block id => [dead embed uuid => media id], in page order.
$map = [
  31588 => [
    '7492151e-a751-4b6a-b555-e1d13575ccf4' => 49574, // Feb 6, Mermer
  ],
  33969 => [
    'c43381eb-df28-4eb8-960d-108d148f61a1' => 49575, // Feb 13, Myers
  ],
  31589 => [
    'e1e9926a-51b1-4a92-9112-4dc547d97daa' => 49578, // Apr 4 special, Forsline
    '0eb2d5b1-2020-49e9-a427-f235dbb17e75' => 49577, // Apr 4, Miranda + Anderson
    '7f694c9e-5618-4a82-89a3-374b611255af' => 49579, // Apr 11, Lange + Keller
    '9e079306-4d4a-45bb-95a3-f2a68e00c449' => 49584, // Apr 18, Dahms-May
    '4dd21cea-f46a-465a-ac67-f8e165223316' => 49586, // Apr 25, Baccin + Braithwaite
    'dbe8c191-67d0-4dc2-be67-b66fe98a2f98' => 49583, // May 2, Cross + Kalauni
  ],
  33970 => [
    'cf43f427-69c1-4ca6-a976-5a4b56abc8f2' => 49582, // May 9, Carlson + Mooneyham
    '38b19126-17c6-4de2-b49b-cbe31b8bb3a2' => 49581, // May 16, Greenhalgh
    '88d2dd48-f9bc-4421-863b-62d01090cb36' => 49585, // May 23, Yoon + Heilsnis
    'abb72de4-17fa-4ff1-8e99-b611d4547d02' => 49587, // May 30, Hidrobo + Getz
    'd9546e71-3d42-4b6b-8f46-5d1cd4d64d20' => 49580, // Jun 6, Wirth + Bell
  ],
  31590 => [
    '3ecd7d56-4020-45b0-8d3d-fe533180d030' => 49588, // Oct 9, Kowalewski
    'b9d6fd82-36da-4b37-b69a-a3511bfb7934' => 49589, // Oct 16, Detweiler
    'b167df28-e226-41d7-b921-cc4090890c56' => 49590, // Oct 23, Allen
    '205a82b9-9109-4de5-835d-2c5a1f760875' => 49592, // Oct 30, McGhee
  ],
  33971 => [
    '2f1e4239-ea27-44a7-bee9-74a11919faa2' => 49591, // Nov 6, Millison
    '040e605f-14ef-486d-a4cd-e2ca54e8e568' => 49597, // Nov 13, Omilani
    'a2a2654e-fdf9-4294-9407-2f3ae234e7bf' => 49594, // Nov 15, Hull
    '8b010017-a239-4df7-8d8e-619ab4ad57b3' => 49593, // Nov 20, Copp
    'b0439301-0b1a-4efb-87aa-055746b91f9c' => 49596, // Nov 27, Selman
    'd9b04739-09bd-4d58-8981-7bff9db5d198' => 49595, // Dec 4, Garcia
  ],
];

$total = 0;
foreach ($map as $bid => $embeds) {
  $block = BlockContent::load($bid);
  $body = $block->get('body')->value;
  $replaced = 0;
  foreach ($embeds as $dead => $mid) {
    $media = Media::load($mid);
    if (!$media || $media->bundle() !== 'kaltura') {
      throw new \RuntimeException("Media $mid missing or not kaltura.");
    }
    $count = substr_count($body, 'data-entity-uuid="' . $dead . '"');
    if ($count === 0) {
      // Already repointed on an earlier run.
      continue;
    }
    $body = str_replace('data-entity-uuid="' . $dead . '"', 'data-entity-uuid="' . $media->uuid() . '"', $body);
    $replaced += $count;
    printf("  block %d: %s -> media %d (%s)\n", $bid, substr($dead, 0, 8), $mid, mb_substr($media->getName(), 0, 70));
  }
  if ($replaced && $apply) {
    $block->get('body')->value = $body;
    $block->setNewRevision(FALSE);
    $block->save();
  }
  $total += $replaced;
}
printf("%s %d embeds.\n", $apply ? 'Repointed' : 'Dry run: would repoint', $total);
