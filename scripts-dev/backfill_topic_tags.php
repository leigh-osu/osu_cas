<?php

/**
 * @file
 * Topic/tag terms onto pages and videos consolidated without them.
 *
 * The D7 topic vocabularies (veg/turf/SWD/nursery tags, COAREC topics,
 * article year, publication type) lived on article, page, video and project
 * nodes. Articles kept theirs through cas_article_to_story; the page and
 * video migrations dropped them, which empties the topic resource listings
 * (articles_by_subject / articles_coarec contexts) of their non-article
 * rows. Copies the terms from the D7 database onto the same-nid D10 nodes
 * (term ids preserved). Idempotent; runs each rebuild.
 *
 * Usage: drush scr scripts-dev/backfill_topic_tags.php
 */

$map = [
  'field_veg_tags' => 'field_tax_veg_topics',
  'field_turf_tags' => 'field_tax_turf_topics',
  'field_swd_tags' => 'field_tax_swd_topics',
  'field_nursery_tags' => 'field_tax_nursery_topics',
  'field_coarec_topics' => 'field_tax_coarec_topics',
  'field_article_year' => 'field_tax_year',
  'field_publication_type_tag' => 'field_tax_publication_type',
];
$migrate = \Drupal\Core\Database\Database::getConnection('default', 'migrate');
$terms = array_flip(array_map('intval', \Drupal::database()->query('SELECT tid FROM {taxonomy_term_field_data}')->fetchCol()));
$storage = \Drupal::entityTypeManager()->getStorage('node');
$set = $same = $skipped = 0;
$by_node = [];
foreach ($map as $d7 => $d10) {
  $rows = $migrate->query("
    SELECT f.entity_id AS nid, f.{$d7}_tid AS tid
    FROM {field_data_$d7} f
    JOIN {node} n ON n.nid = f.entity_id
    WHERE f.entity_type = 'node' AND n.type IN ('page', 'video', 'project')
    ORDER BY f.entity_id, f.delta
  ")->fetchAll();
  foreach ($rows as $row) {
    $by_node[$row->nid][$d10][] = (int) $row->tid;
  }
}
foreach ($storage->loadMultiple(array_keys($by_node)) as $node) {
  $changed = FALSE;
  foreach ($by_node[$node->id()] as $field => $tids) {
    if (!$node->hasField($field)) {
      $skipped++;
      continue;
    }
    $tids = array_values(array_filter($tids, fn($t) => isset($terms[$t])));
    $current = array_map('intval', array_column($node->get($field)->getValue(), 'target_id'));
    if ($current === $tids) {
      continue;
    }
    $node->set($field, $tids);
    $changed = TRUE;
  }
  if ($changed) {
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    $set++;
  }
  else {
    $same++;
  }
}
printf("Nodes updated: %d  Already correct: %d  Field-missing values: %d\n", $set, $same, $skipped);
