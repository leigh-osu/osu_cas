<?php

/**
 * @file
 * Strip dead legacy file references from migrated rich text.
 *
 * After CasLegacyFilePaths has rewritten every resolvable legacy URL
 * (migration-time, plus the re-pass this script starts with), any local
 * /sites/{agscid7,agsci,default}/files/ URL still present points at a
 * file that exists neither in D10, the D7 files tree, nor live D7.
 * Broken <img> tags are removed and dead <a> links unwrapped to their
 * label text (captions and surrounding prose are kept). External hosts
 * are never touched. Idempotent; runs in rebuild_site.sh section 7
 * after the D7-source refresh, so anything recoverable is recovered
 * first.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/strip_dead_legacy_refs.php
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Database;
use Drupal\osu_migrations_cas\Plugin\migrate\process\CasLegacyFilePaths;

$db = Database::getConnection();

// A URL is "ours and legacy" when relative (or on an agsci host) under one
// of the legacy site dirs. Mirrors CasLegacyFilePaths' matching.
$is_dead_local = function (string $url): bool {
  if (!preg_match('~^(?:https?://([^/]+))?/sites/(agscid7|agsci|default)/files/~i', $url, $m)) {
    return FALSE;
  }
  // The agscid7/agsci dirs are unambiguously this site whatever the host
  // (department domains serve the same tree); only sites/default needs the
  // host guard, since other OSU sites use it too.
  if (strcasecmp($m[2], 'default') === 0 && !empty($m[1]) && stripos($m[1], 'agsci') === FALSE) {
    return FALSE;
  }
  return TRUE;
};

$strip = function (string $html) use ($is_dead_local): string {
  $dom = Html::load($html);
  $xpath = new \DOMXPath($dom);
  $changed = FALSE;
  foreach (iterator_to_array($xpath->query('//img[@src]')) as $img) {
    $src = $img->getAttribute('src');
    // D7 file-type icons (/modules/file/icons/*.png) do not exist in D10;
    // they were decorative markers before file/video links.
    if ($is_dead_local($src) || str_starts_with($src, '/modules/file/icons/')) {
      $img->parentNode->removeChild($img);
      $changed = TRUE;
    }
  }
  foreach (iterator_to_array($xpath->query('//a[@href]')) as $a) {
    if (!$is_dead_local($a->getAttribute('href'))) {
      continue;
    }
    // Unwrap: keep the link's children (label text, images) in place.
    while ($a->firstChild) {
      $a->parentNode->insertBefore($a->firstChild, $a);
    }
    $a->parentNode->removeChild($a);
    $changed = TRUE;
  }
  return $changed ? Html::serialize($dom) : $html;
};

$targets = [
  ['node__body', 'node_revision__body', 'body_value'],
  ['block_content__body', 'block_content_revision__body', 'body_value'],
  ['paragraph__field_p_accordion_body', 'paragraph_revision__field_p_accordion_body', 'field_p_accordion_body_value'],
];

$rewritten = $stripped = 0;
foreach ($targets as [$table, $revision_table, $column]) {
  foreach ([$table, $revision_table] as $t) {
    $rows = $db->query("SELECT entity_id, revision_id, delta, langcode, $column AS v FROM $t WHERE $column LIKE :p1 OR $column LIKE :p2 OR $column LIKE :p3 OR $column LIKE :p4", [
      ':p1' => '%/sites/agscid7/files/%',
      ':p2' => '%/sites/agsci/files/%',
      ':p3' => '%/sites/default/files/%',
      ':p4' => '%/modules/file/icons/%',
    ])->fetchAll();
    foreach ($rows as $row) {
      // Recover anything the refreshed D7 tree can now resolve...
      $new = CasLegacyFilePaths::rewriteText($row->v);
      if ($new !== $row->v) {
        $rewritten++;
      }
      // ...then strip what is still dead.
      $final = $strip($new);
      if ($final !== $new) {
        $stripped++;
      }
      if ($final !== $row->v) {
        $db->update($t)
          ->fields([$column => $final])
          ->condition('entity_id', $row->entity_id)
          ->condition('revision_id', $row->revision_id)
          ->condition('delta', $row->delta)
          ->condition('langcode', $row->langcode)
          ->execute();
      }
    }
    print "$t: " . count($rows) . " candidate rows\n";
  }
}
print "Done: $rewritten rows recovered by the resolver, $stripped rows had dead refs stripped.\n";
$left = $db->query("SELECT COUNT(*) FROM node__body WHERE body_value LIKE :p1 OR body_value LIKE :p2", [':p1' => '%/sites/agscid7/files/%', ':p2' => '%/sites/agsci/files/%'])->fetchField();
print "node__body rows still referencing legacy paths: $left\n";
