<?php

/**
 * @file
 * One-off recovery: flip cas-button-light back to cas-button-dark where the
 * D7 source button was orange (btn-primary / btn-osu / osu-btn-primary).
 *
 * The first normalize_legacy_buttons.php sweep mapped every legacy variant
 * to cas-button-light before the "orange stays orange" rule landed, erasing
 * the variant classes from current AND revision tables. The D7 source text
 * still has them, so this matches buttons across databases by a normalized
 * (href, label) key: phase 1 scans every D7 rich-text column for button
 * anchors and records which keys were orange and which were not; phase 2
 * rewrites D10 rows, flipping cas-button-light to cas-button-dark only for
 * keys that were exclusively orange in D7. Keys seen both ways in D7 are
 * ambiguous (positional info is gone) and are reported, not flipped.
 * Idempotent. Rebuilds don't need this — the migration transform now maps
 * orange variants directly.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/recover_orange_buttons.php
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Database;

/**
 * Normalizes an href + label into a cross-database match key.
 *
 * Hrefs were rewritten during migration (legacy file paths, imagecache,
 * aliases), so: strip scheme/host for oregonstate/ddev hosts, use the
 * basename for /files/ paths, decode entities, drop trailing slashes.
 */
function _btn_key(string $href, string $label): string {
  $href = html_entity_decode(trim($href));
  $href = preg_replace('~^https?://(?:[a-z0-9.-]*oregonstate\.edu|ddev\.agsci\.oregonstate\.edu|osu-cas\.ddev\.site)~i', '', $href);
  if (preg_match('~/files/~i', $href)) {
    $href = strtolower(rawurldecode(basename(parse_url($href, PHP_URL_PATH) ?? $href)));
  }
  else {
    $href = strtolower(rtrim($href, '/'));
  }
  $label = mb_strtolower(trim(preg_replace('~\s+~u', ' ', $label)));
  return $href . '|' . $label;
}

/**
 * Extracts [key => is_orange][] entries for every button anchor in $text.
 */
function _btn_scan(string $text): array {
  if (stripos($text, 'btn') === FALSE) {
    return [];
  }
  $found = [];
  $dom = Html::load($text);
  $xpath = new \DOMXPath($dom);
  foreach ($xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " btn ")]') as $el) {
    $classes = ' ' . preg_replace('~\s+~', ' ', trim($el->getAttribute('class'))) . ' ';
    $orange = (bool) preg_match('~ (btn-primary;?|btn-osu|osu-btn-primary) ~', $classes);
    $found[] = [_btn_key($el->getAttribute('href'), $el->textContent), $orange];
  }
  return $found;
}

$migrate = Database::getConnection('default', 'migrate');
$db = Database::getConnection();

// -- Phase 1: registry of D7 button keys ------------------------------------
$d7_orange = [];
$d7_other = [];
$d7_tables = $migrate->query(
  "SELECT c.TABLE_NAME, c.COLUMN_NAME FROM information_schema.COLUMNS c
   WHERE c.TABLE_SCHEMA = DATABASE()
     AND c.DATA_TYPE IN ('text', 'mediumtext', 'longtext')
     AND (c.TABLE_NAME LIKE 'field_data_%' AND c.COLUMN_NAME LIKE '%_value'
          OR c.TABLE_NAME = 'block_custom' AND c.COLUMN_NAME = 'body')"
)->fetchAll();

$scanned = 0;
foreach ($d7_tables as $t) {
  $rows = $migrate->query(
    "SELECT `{$t->COLUMN_NAME}` AS v FROM `{$t->TABLE_NAME}`
     WHERE `{$t->COLUMN_NAME}` LIKE '%btn%'"
  )->fetchCol();
  foreach ($rows as $v) {
    $scanned++;
    foreach (_btn_scan($v) as [$key, $orange]) {
      if ($orange) {
        $d7_orange[$key] = TRUE;
      }
      else {
        $d7_other[$key] = TRUE;
      }
    }
  }
}
$ambiguous = array_intersect_key($d7_orange, $d7_other);
$flip = array_diff_key($d7_orange, $ambiguous);
print 'D7: ' . $scanned . " rows scanned, " . count($d7_orange) . " orange keys, "
  . count($d7_other) . " non-orange keys, " . count($ambiguous) . " ambiguous (skipped)\n";

// -- Phase 2: flip matching D10 buttons -------------------------------------
$targets = [
  ['node__body', 'node_revision__body', 'body_value'],
  ['block_content__body', 'block_content_revision__body', 'body_value'],
  ['paragraph__field_p_accordion_body', 'paragraph_revision__field_p_accordion_body', 'field_p_accordion_body_value'],
];

$updated = 0;
$flipped = 0;
$unmatched = [];
foreach ($targets as [$table, $revision_table, $column]) {
  foreach ([$table, $revision_table] as $t) {
    $rows = $db->query("SELECT entity_id, revision_id, delta, langcode, $column AS v FROM $t WHERE $column LIKE :p", [':p' => '%cas-button-light%'])->fetchAll();
    foreach ($rows as $row) {
      $dom = Html::load($row->v);
      $xpath = new \DOMXPath($dom);
      $changed = FALSE;
      foreach ($xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " cas-button-light ")]') as $el) {
        $key = _btn_key($el->getAttribute('href'), $el->textContent);
        if (isset($flip[$key])) {
          $el->setAttribute('class', trim(preg_replace('~\bcas-button-light\b~', 'cas-button-dark', $el->getAttribute('class'))));
          $changed = TRUE;
          $flipped++;
        }
        elseif (!isset($d7_other[$key]) && !isset($ambiguous[$key])) {
          $unmatched[$key] = ($unmatched[$key] ?? 0) + 1;
        }
      }
      if ($changed) {
        $db->update($t)
          ->fields([$column => Html::serialize($dom)])
          ->condition('entity_id', $row->entity_id)
          ->condition('revision_id', $row->revision_id)
          ->condition('delta', $row->delta)
          ->condition('langcode', $row->langcode)
          ->execute();
        $updated++;
      }
    }
    print "$t: " . count($rows) . " candidate rows\n";
  }
}
print "Done: $flipped buttons flipped to cas-button-dark across $updated rows.\n";
print count($unmatched) . " D10 button keys had no D7 match (left light).\n";
if ($ambiguous) {
  print "Ambiguous keys (appeared orange AND non-orange in D7, left light):\n";
  foreach (array_slice(array_keys($ambiguous), 0, 40) as $k) {
    print "  $k\n";
  }
}
