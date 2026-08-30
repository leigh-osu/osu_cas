<?php

/**
 * @file
 * MMI external-story treatment: resolve targets, pre-create redirects.
 *
 * The MMI adaptation of fix_external_stories.php. 405 of the 441 migrated
 * MMI stories carry field_osu_story_external_url (press coverage). The
 * osu_story save hooks do not fire during migration, so:
 * 1. Targets pointing at mmi.oregonstate.edu (24) — the D7 site today,
 *    THIS site at cutover — are resolved first:
 *    - target is the story itself (via alias/redirect): clear the field —
 *      it would loop at cutover;
 *    - target resolves in D10 (alias or redirect): left alone;
 *    - D7 file URL: rewritten to the local file (verbatim uris; copied
 *      from the mounted D7 tree if the migration did not bring it over);
 *    - everything else: follow live D7 — dead targets clear the field,
 *      off-site forwards are adopted, leftovers go to the triage CSV.
 * 2. Every remaining external MMI story without a redirect is re-saved
 *    with auto_forward_external temporarily ON, so the osu_story hook
 *    creates its redirect/metatag/sitemap treatment now — the same
 *    pre-creation the agsci rebuild did, keeping the eventual flag flip
 *    instant and uniform across both sites. The flag is restored after.
 *
 * Idempotent. Run via mmi_migrate.sh section 10.
 */

use Drupal\node\Entity\Node;

const MMI_D7_FILES = '/var/www/d7/sites/mmi7/files/';

$db = \Drupal::database();
$alias_mgr = \Drupal::service('path_alias.manager');
$redirect_repo = \Drupal::service('redirect.repository');
$fs = \Drupal::service('file_system');

$rows = $db->query(
  "SELECT u.entity_id nid, u.field_osu_story_external_url_uri uri
   FROM {node__field_osu_story_external_url} u
   WHERE u.bundle = 'story' AND u.entity_id >= 400000"
)->fetchAll();
print count($rows) . " MMI external stories\n";

/**
 * Rewrites a story's external URL and any existing redirect together.
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

/**
 * Clears a story's external URL and deletes its redirect.
 */
$clear = function (int $nid) use ($redirect_repo): void {
  $node = Node::load($nid);
  $node->set('field_osu_story_external_url', []);
  foreach ($redirect_repo->findBySourcePath("node/$nid") as $redirect) {
    $redirect->delete();
  }
  $node->save();
};

// ---- Pass 1: mmi.oregonstate.edu targets ----------------------------------
$stats = ['self' => 0, 'ok_at_cutover' => 0, 'file_rewritten' => 0, 'file_copied' => 0, 'cleared_dead' => 0, 'rewritten_external' => 0, 'unresolved' => []];
$client = \Drupal::httpClient();

foreach ($rows as $r) {
  $p = parse_url($r->uri);
  if (!preg_match('~^(www\.)?mmi\.oregonstate\.edu$~', $p['host'] ?? '')) {
    continue;
  }
  if (str_starts_with($r->uri, 'internal:')) {
    // Already rewritten to a local file on a previous run.
    continue;
  }
  $path = preg_replace('~/{2,}~', '/', $p['path'] ?? '/');

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
    $clear($r->nid);
    $stats['self']++;
    continue;
  }
  if ($resolved_nid !== NULL) {
    $stats['ok_at_cutover']++;
    continue;
  }

  // D7 file URL: rewrite to the local file (uris are verbatim), copying
  // from the mounted D7 tree if the migration did not bring it over.
  if (preg_match('~^/sites/(mmi7|mmi|default)/files/(.+)$~', $path, $m)) {
    $sub = rawurldecode($m[2]);
    $local = 'public://' . $sub;
    if (!file_exists($local) && file_exists(MMI_D7_FILES . $sub)) {
      $dir = dirname($local);
      $fs->prepareDirectory($dir, 1 | 2);
      if (@copy(MMI_D7_FILES . $sub, $local)) {
        $stats['file_copied']++;
      }
    }
    if (file_exists($local)) {
      $rewrite($r->nid, 'internal:/sites/agsci.oregonstate.edu/files/' . str_replace('%2F', '/', rawurlencode($sub)));
      $stats['file_rewritten']++;
      continue;
    }
  }

  // Last resort: what does live D7 mmi say?
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
    $clear($r->nid);
    $stats['cleared_dead']++;
    continue;
  }
  $fh = parse_url($final, PHP_URL_HOST) ?? '';
  if ($final !== $r->uri && !preg_match('~mmi\.oregonstate\.edu~', $fh)) {
    $rewrite($r->nid, $final);
    $stats['rewritten_external']++;
    continue;
  }
  // D7 serves (or forwards) an mmi page: adopt it if that path resolves in
  // D10 — it keeps working today and after cutover.
  $final_path = preg_replace('~/{2,}~', '/', parse_url($final, PHP_URL_PATH) ?? '/');
  $fi = $alias_mgr->getPathByAlias(rawurldecode($final_path));
  if (preg_match('~^/node/\d+$~', $fi) || $redirect_repo->findMatchingRedirect(ltrim($final_path, '/'), [])) {
    $rewrite($r->nid, 'https://mmi.oregonstate.edu' . $final_path);
    $stats['rewritten_external']++;
    continue;
  }
  $stats['unresolved'][] = [$r->nid, $r->uri, $code, $final];
}

print "self-loop cleared: {$stats['self']}\n";
print "resolve in D10, left alone: {$stats['ok_at_cutover']}\n";
print "file targets rewritten to local: {$stats['file_rewritten']} (files copied from D7 tree: {$stats['file_copied']})\n";
print "dead on live D7, field cleared: {$stats['cleared_dead']}\n";
print "rewritten to D7's own forward: {$stats['rewritten_external']}\n";
print "unresolved (manual triage): " . count($stats['unresolved']) . "\n";
if ($stats['unresolved']) {
  $csv = '/var/www/html/files/mmi_external_stories_unresolved.csv';
  @mkdir(dirname($csv), 0777, TRUE);
  $fh = fopen($csv, 'w');
  fputcsv($fh, ['nid', 'target', 'd7_status', 'd7_final_url']);
  foreach ($stats['unresolved'] as $row) {
    fputcsv($fh, $row);
  }
  fclose($fh);
  print "  -> $csv\n";
}

// ---- Pass 2: pre-create the redirect treatment ----------------------------
// The osu_story save hooks are gated by auto_forward_external (FALSE on the
// live site until go-live). Flip it on for just this pass so the hook builds
// each story's redirect/metatags/sitemap suppression, exactly as the agsci
// rebuild pre-created them; the module's own catch-up covers later edits.
$config = \Drupal::configFactory()->getEditable('osu_story.settings');
$original = $config->get('auto_forward_external');
$config->set('auto_forward_external', TRUE)->save();
$backfilled = 0;
try {
  $current = $db->query(
    "SELECT u.entity_id FROM {node__field_osu_story_external_url} u
     WHERE u.bundle = 'story' AND u.entity_id >= 400000"
  )->fetchCol();
  $current_uris = $db->query(
    "SELECT u.entity_id, u.field_osu_story_external_url_uri FROM {node__field_osu_story_external_url} u
     WHERE u.bundle = 'story' AND u.entity_id >= 400000"
  )->fetchAllKeyed();
  foreach ($current as $nid) {
    if (!empty($redirect_repo->findBySourcePath("node/$nid"))) {
      continue;
    }
    // A schemeless uri would make the osu_story hook fatal in Url::fromUri;
    // mmi_external_url normalizes these in-migration, so any survivor is a
    // regression worth seeing, not crashing on.
    if (!preg_match('~^[a-z][a-z0-9+.-]*:~i', (string) ($current_uris[$nid] ?? ''))) {
      print "  WARNING: story $nid has a schemeless external url '" . ($current_uris[$nid] ?? '') . "'; skipped\n";
      continue;
    }
    $node = Node::load($nid);
    if ($node) {
      $node->save();
      $backfilled++;
    }
  }
}
finally {
  $config->set('auto_forward_external', $original)->save();
}
print "redirects pre-created by re-save: $backfilled (auto_forward_external restored to " . var_export($original, TRUE) . ")\n";
