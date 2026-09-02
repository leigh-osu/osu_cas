<?php

/**
 * Weather daily data clean-up to run on prod after commit 368c347f1c deploys.
 *
 * That commit restores the D7 auto_nodetitle patterns as auto_entitylabel
 * config for weather_daily_data / weather_monthly_data and makes the station
 * (location) field required. This script repeats the content fixes that were
 * made locally on 2026-09-02 against the same node ids:
 *
 *   1. Resave every daily node created since go-live whose title was typed by
 *      hand ("8-27-26", "August 9, 2026"...) so auto_entitylabel regenerates
 *      it. Legacy pre-launch nodes are deliberately left alone: the old Hyslop
 *      "weather_data" rows carry titles like "March 2012 - Summary".
 *   2. Delete node 302496, a location-less duplicate of 302531 (Hyslop
 *      2026-08-10, identical readings).
 *   3. Backfill the station on the 13 daily nodes that never had one, using
 *      the weather-station group each belongs to (3986 Hyslop, 110936
 *      Malheur). D7 had these empty as well.
 *   4. Delete the ten Hyslop duplicates that surfaced once those 13 had a
 *      station: five identical extras (oldest node kept) and five backfilled
 *      copies whose readings differ from the entry that already had the
 *      station.
 *
 * Every delete is guarded by a check of the node's type, date and station so
 * a stray id on prod cannot remove the wrong thing. Saves are in place (no
 * new revisions). Idempotent: re-runs find nothing to do.
 *
 * scripts-dev/ is not deployed to prod, so copy the script over first:
 *
 *   scp scripts-dev/fix_weather_daily_titles.php osucas.prod@osucasprod.ssh.prod.acquia-sites.com:
 *   ddev drush @osucas.prod scr /home/osucas.prod/fix_weather_daily_titles.php              (dry run)
 *   ddev drush @osucas.prod scr /home/osucas.prod/fix_weather_daily_titles.php -- --apply
 */

use Drupal\node\Entity\Node;

$apply = in_array('--apply', $extra ?? [], TRUE);
$db = \Drupal::database();
$dry_deleted = [];
$backfilled = [];
$mode = $apply ? 'APPLY' : 'DRY RUN';
print "== $mode ==\n";

// Guard: the auto-title config has to be active or the resaves do nothing.
foreach (['weather_daily_data', 'weather_monthly_data'] as $type) {
  $c = \Drupal::config("auto_entitylabel.settings.node.$type");
  if ((int) $c->get('status') !== 1 || !$c->get('pattern')) {
    print "ABORT: auto_entitylabel.settings.node.$type is not active - has the config deployed?\n";
    return;
  }
}

$station_of_group = [3986 => 'hyslop', 110936 => 'malheur'];
$group_of = function (int $nid) use ($db): ?int {
  $gid = $db->query("SELECT gid FROM {group_relationship_field_data} WHERE entity_id = :n AND plugin_id = 'group_node:weather_daily_data'", [':n' => $nid])->fetchField();
  return $gid ? (int) $gid : NULL;
};
$is_daily = function (?Node $n, string $date, ?string $loc): bool {
  return $n && $n->bundle() === 'weather_daily_data'
    && $n->get('field_dw_date')->value === $date
    && $n->get('field_dw_location')->value === $loc;
};

// 1. Post-launch daily nodes with hand-typed titles.
print "\n-- 1. Resave post-launch daily nodes with hand-typed titles\n";
$nids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'weather_daily_data')
  ->condition('nid', 300000, '>')
  ->condition('title', 'Weather Data for %', 'NOT LIKE')
  ->execute();
print count($nids) . " node(s)\n";
foreach (Node::loadMultiple($nids) as $n) {
  $old = $n->label();
  if ($apply) {
    $n->setNewRevision(FALSE);
    $n->save();
  }
  print "  {$n->id()}  " . str_pad($old, 18) . ' => ' . ($apply ? $n->label() : '(regenerated)') . "\n";
}

// 2. Location-less duplicate of 302531.
print "\n-- 2. Delete location-less duplicate 302496\n";
$n = Node::load(302496);
$twin = Node::load(302531);
if ($is_daily($n, '2026-08-10', NULL) && $is_daily($twin, '2026-08-10', 'hyslop')) {
  if ($apply) {
    $n->delete();
  }
  $dry_deleted[302496] = TRUE;
  print "  302496 deleted (twin 302531 kept)\n";
}
else {
  print '  skipped: ' . ($n ? 'node does not match expected date/empty station' : 'already gone') . "\n";
}

// 3. Backfill station from group membership.
print "\n-- 3. Backfill station on daily nodes without one\n";
$nids = $db->query("SELECT n.nid FROM {node_field_data} n LEFT JOIN {node__field_dw_location} l ON l.entity_id = n.nid WHERE n.type = 'weather_daily_data' AND l.entity_id IS NULL ORDER BY n.nid")->fetchCol();
print count($nids) . " node(s)\n";
foreach (Node::loadMultiple($nids) as $n) {
  if (!empty($dry_deleted[$n->id()])) {
    print "  {$n->id()}  (removed in step 2)\n";
    continue;
  }
  $loc = $station_of_group[$group_of((int) $n->id())] ?? NULL;
  if (!$loc) {
    print "  {$n->id()}  SKIP: not in a known weather-station group\n";
    continue;
  }
  if ($apply) {
    $n->set('field_dw_location', $loc);
    $n->setNewRevision(FALSE);
    $n->save();
  }
  // Remembered so the dry run's step 4 sees the station this step would set.
  $backfilled[$n->id()] = $loc;
  print "  {$n->id()}  " . $n->get('field_dw_date')->value . " => $loc\n";
}

// 4. Hyslop duplicates. [nid to delete => date]; the kept node is listed for
// the guard so nothing is removed unless its twin is present.
print "\n-- 4. Delete Hyslop duplicates\n";
$dupes = [
  // Identical readings, oldest kept.
  244556 => ['2021-01-22', 228841],
  237551 => ['2021-03-28', 230521],
  237566 => ['2021-03-28', 230521],
  270731 => ['2024-10-01', 270791],
  279226 => ['2025-09-02', 279251],
  // Differing readings, the entry that already had the station kept.
  232846 => ['2021-06-18', 237286],
  232856 => ['2021-06-20', 237281],
  234506 => ['2021-08-07', 234516],
  255026 => ['2023-03-22', 255526],
  259166 => ['2023-07-30', 259946],
];
foreach ($dupes as $nid => [$date, $keep]) {
  $n = Node::load($nid);
  if (!$n) {
    print "  $nid  already gone\n";
    continue;
  }
  $kept = Node::load($keep);
  // In a dry run the station from step 3 was never saved; count it as set.
  $has_station = $is_daily($n, $date, 'hyslop')
    || (!$apply && $is_daily($n, $date, NULL) && ($backfilled[$nid] ?? NULL) === 'hyslop');
  if (!$has_station || !$is_daily($kept, $date, 'hyslop')) {
    print "  $nid  SKIP: node or its kept twin $keep does not match Hyslop $date\n";
    continue;
  }
  if ($apply) {
    $n->delete();
  }
  print "  $nid  deleted ($date, kept $keep)\n";
}

// Final state.
$missing = $db->query("SELECT COUNT(*) FROM {node_field_data} n LEFT JOIN {node__field_dw_location} l ON l.entity_id = n.nid WHERE n.type = 'weather_daily_data' AND l.entity_id IS NULL")->fetchField();
$untitled = $db->query("SELECT COUNT(*) FROM {node_field_data} WHERE type = 'weather_daily_data' AND nid > 300000 AND title NOT LIKE 'Weather Data for %'")->fetchField();
print "\n== done ($mode): $missing daily node(s) without a station, $untitled post-launch node(s) with hand-typed titles\n";
