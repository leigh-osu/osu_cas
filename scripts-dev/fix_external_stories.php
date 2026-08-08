<?php

/**
 * @file
 * Post-rebuild fix: external-story redirects and D7-host targets.
 *
 * From the External Stories report (findings 1 & 2):
 * - ~400 stories carry field_osu_story_external_url but no redirect
 *   entity (the osu_story save hook partially failed during migration):
 *   re-save them so the hook creates the redirect idempotently.
 * - ~870 targets point at agsci.oregonstate.edu — the D7 site today, but
 *   THIS site at cutover. Resolve them now:
 *   - target is the story itself (via alias/redirect): clear the field
 *     and delete the node's redirect — it would loop at cutover;
 *   - target resolves in D10 (alias or redirect): leave it — it will
 *     keep working when the hostname flips;
 *   - target is a D7 file URL: rewrite field + redirect to the local
 *     file path, copying the file from the mounted D7 tree when the
 *     migration did not bring it over;
 *   - everything else: follow live D7 — dead targets clear the field
 *     (the story renders as a normal local page, like D7 does for a
 *     404-target), survivors go to the triage CSV.
 * Idempotent; writes files/external_stories_unresolved.csv (move to the reports dir)
 * only when leftovers exist (path printed at the end).
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_external_stories.php
 */

use Drupal\node\Entity\Node;

const D7_FILES = '/var/www/d7/sites/agscid7/files/';

$db = \Drupal::database();
$alias_mgr = \Drupal::service('path_alias.manager');
$redirect_repo = \Drupal::service('redirect.repository');
$fs = \Drupal::service('file_system');

$rows = $db->query(
  "SELECT u.entity_id nid, u.field_osu_story_external_url_uri uri
   FROM node__field_osu_story_external_url u WHERE u.bundle = 'story'"
)->fetchAll();
print count($rows) . " external stories\n";

// ---- Finding 1: create the missing redirects by re-saving ------------------
$backfilled = 0;
foreach ($rows as $r) {
  if (empty($redirect_repo->findBySourcePath("node/{$r->nid}"))) {
    $node = Node::load($r->nid);
    if ($node) {
      $node->save();
      $backfilled++;
    }
  }
}
print "redirects backfilled by re-save: $backfilled\n";

/**
 * Rewrites a story's external URL and its redirect target together.
 */
$rewrite = function (int $nid, string $new_uri) use ($redirect_repo): void {
  $node = Node::load($nid);
  $node->set('field_osu_story_external_url', ['uri' => $new_uri]);
  foreach ($redirect_repo->findBySourcePath("node/$nid") as $redirect) {
    $redirect->setRedirect(preg_replace('~^internal:~', '', $new_uri));
    $redirect->save();
  }
  $node->save();
};

// ---- Finding 2: agsci-host targets ----------------------------------------
$stats = ['self' => 0, 'ok_at_cutover' => 0, 'file_rewritten' => 0, 'file_copied' => 0, 'cleared_dead' => 0, 'rewritten_external' => 0, 'unresolved' => []];
$client = \Drupal::httpClient();

foreach ($rows as $r) {
  $p = parse_url($r->uri);
  if (!preg_match('~^(www\.)?agsci\.oregonstate\.edu$~', $p['host'] ?? '')) {
    continue;
  }
  $path = preg_replace('~/{2,}~', '/', $p['path'] ?? '/');

  // Already rewritten to a local file on a previous run.
  if (str_starts_with($r->uri, 'internal:')) {
    continue;
  }

  // Resolves in D10 (alias or redirect)?
  $internal = $alias_mgr->getPathByAlias($path);
  $resolved_nid = NULL;
  if (preg_match('~^/node/(\d+)$~', $internal, $m)) {
    $resolved_nid = (int) $m[1];
  }
  elseif ($red = $redirect_repo->findMatchingRedirect(ltrim($path, '/'), [])) {
    $resolved_nid = preg_match('~/node/(\d+)~', $red->getRedirect()['uri'] ?? '', $m2) ? (int) $m2[1] : -1;
  }

  if ($resolved_nid === (int) $r->nid) {
    // Self-loop at cutover: the story IS its own target now.
    $node = Node::load($r->nid);
    $node->set('field_osu_story_external_url', []);
    foreach ($redirect_repo->findBySourcePath("node/{$r->nid}") as $redirect) {
      $redirect->delete();
    }
    $node->save();
    $stats['self']++;
    continue;
  }
  if ($resolved_nid !== NULL) {
    $stats['ok_at_cutover']++;
    continue;
  }

  // D7 file URL: rewrite to the local file, copying from the D7 tree if the
  // migration did not bring it over.
  if (preg_match('~^/sites/(agscid7|default|agsci\.oregonstate\.edu)/files/(.+)$~', $path, $m)) {
    $sub = rawurldecode($m[2]);
    $local = 'public://' . $sub;
    if (!file_exists($local) && file_exists(D7_FILES . $sub)) {
      $dir = dirname($local);
      $fs->prepareDirectory($dir, 1 | 2);
      if (@copy(D7_FILES . $sub, $local)) {
        $stats['file_copied']++;
      }
    }
    if (file_exists($local)) {
      $rewrite($r->nid, 'internal:/sites/agsci.oregonstate.edu/files/' . str_replace('%2F', '/', rawurlencode($sub)));
      $stats['file_rewritten']++;
      continue;
    }
  }

  // Last resort: what does live D7 say?
  try {
    $resp = $client->get($r->uri, ['timeout' => 15, 'allow_redirects' => ['max' => 5, 'track_redirects' => TRUE], 'http_errors' => FALSE]);
    $code = $resp->getStatusCode();
    $history = $resp->getHeader('X-Guzzle-Redirect-History');
    $final = $history ? end($history) : $r->uri;
  }
  catch (\Exception $e) {
    $code = 0;
    $final = $r->uri;
  }
  if ($code >= 400 || $code === 0) {
    // Dead on D7 too: the story becomes an ordinary local page.
    $node = Node::load($r->nid);
    $node->set('field_osu_story_external_url', []);
    foreach ($redirect_repo->findBySourcePath("node/{$r->nid}") as $redirect) {
      $redirect->delete();
    }
    $node->save();
    $stats['cleared_dead']++;
    continue;
  }
  $fh = parse_url($final, PHP_URL_HOST) ?? '';
  if ($final !== $r->uri && !preg_match('~agsci\.oregonstate\.edu~', $fh)) {
    // D7 forwards it off-site: keep the final external destination.
    $rewrite($r->nid, $final);
    $stats['rewritten_external']++;
    continue;
  }
  // D7 forwards (or serves) an agsci page: if that path resolves in D10,
  // adopt it — it keeps working today and after cutover.
  $final_path = preg_replace('~/{2,}~', '/', parse_url($final, PHP_URL_PATH) ?? '/');
  $fi = $alias_mgr->getPathByAlias(rawurldecode($final_path));
  if (preg_match('~^/node/\d+$~', $fi) || $redirect_repo->findMatchingRedirect(ltrim($final_path, '/'), [])) {
    $rewrite($r->nid, 'https://agsci.oregonstate.edu' . $final_path);
    $stats['rewritten_external']++;
    continue;
  }
  $stats['unresolved'][] = [$r->nid, $r->uri, $code, $final];
}

print "self-loop cleared: {$stats['self']}\n";
print "resolve in D10, left alone: {$stats['ok_at_cutover']}\n";
print "file targets rewritten to local: {$stats['file_rewritten']} (files copied from D7 tree: {$stats['file_copied']})\n";
print "dead on live D7, field cleared: {$stats['cleared_dead']}\n";
print "rewritten to D7's own external forward: {$stats['rewritten_external']}\n";
print "unresolved (manual triage): " . count($stats['unresolved']) . "\n";
if ($stats['unresolved']) {
  $csv = '/var/www/html/files/external_stories_unresolved.csv';
  @mkdir(dirname($csv), 0777, TRUE);
  $fh = fopen($csv, 'w');
  fputcsv($fh, ['nid', 'target', 'd7_status', 'd7_final_url']);
  foreach ($stats['unresolved'] as $row) {
    fputcsv($fh, $row);
  }
  fclose($fh);
  print "  -> $csv\n";
}
