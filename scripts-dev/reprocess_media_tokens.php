<?php

/**
 * @file
 * Re-run the D7 media-token conversion over already-migrated rich text.
 *
 * Bodies that embedded PRIVATE-scheme images kept their raw
 * [[{"fid":...}]] tokens: the stock media migrations cover scheme: public
 * only, so OsuMediaEmbed had nothing to resolve them to at migration time.
 * With cas_media_private_images imported (and the lookup patch in place),
 * this pass converts whatever tokens are now resolvable; tokens that still
 * fail lookup are left untouched and reported.
 *
 * Idempotent. Run after cas_media_private_images:
 *   ddev drush --uri=agsci.oregonstate.edu scr scripts-dev/reprocess_media_tokens.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\node\Entity\Node;

$embed = \Drupal::service('osu_migrations.osu_media_embed');
$db = \Drupal::database();

$targets = [
  ['node', 'node__body', Node::class],
  ['block_content', 'block_content__body', BlockContent::class],
];

$converted = 0;
$unresolved = [];
foreach ($targets as [$type, $table, $class]) {
  $ids = $db->query("SELECT DISTINCT entity_id FROM {" . $table . "} WHERE body_value LIKE :t", [':t' => '%[[{"fid%'])->fetchCol();
  foreach ($ids as $id) {
    $entity = $class::load($id);
    if (!$entity || $entity->get('body')->isEmpty()) {
      continue;
    }
    $item = $entity->get('body')->first()->getValue();
    $new = $embed->transformEmbedCode($item['value']);
    // Tokens whose fid has no D7 file_managed row were dead links BEFORE
    // migration — there is nothing to resolve them to, and leaving them
    // renders raw JSON. Strip those (only those) outright.
    if (str_contains($new, '[[{"fid')) {
      $d7 = \Drupal\Core\Database\Database::getConnection('default', 'migrate');
      $new = preg_replace_callback('/\[\[\{"fid":"?(\d+)"?.*?\]\]/s', function ($m) use ($d7) {
        $exists = $d7->query('SELECT 1 FROM {file_managed} WHERE fid = :fid', [':fid' => $m[1]])->fetchField();
        if (!$exists) {
          print "  stripped dead-in-D7 token (fid {$m[1]})\n";
          return '';
        }
        return $m[0];
      }, $new);
    }
    if ($new !== $item['value']) {
      $item['value'] = $new;
      $entity->get('body')->setValue([$item]);
      $entity->setNewRevision(FALSE);
      $entity->save();
      $converted++;
      print "converted: $type $id\n";
    }
    if (str_contains($new, '[[{"fid')) {
      preg_match_all('/"fid":"?(\d+)/', $new, $m);
      $unresolved[] = "$type $id (fids " . implode(',', array_unique($m[1])) . ")";
    }
  }
}

print "\n$converted entities updated.\n";
if ($unresolved) {
  print "Still carrying unresolvable tokens:\n  " . implode("\n  ", $unresolved) . "\n";
}
else {
  print "No raw tokens remain.\n";
}
