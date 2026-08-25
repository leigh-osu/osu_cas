<?php

/**
 * @file
 * MMI user reconciliation audit (mmi-migration step: users before content).
 *
 * Joins the MMI D7 users against the live D10 accounts over three signals --
 * D7 cas_user.cas_name vs authmap.authname, mail vs mail, and mail local part
 * vs authname -- and prints one decision row per user. Output feeds the
 * pre-seed of migrate_map_mmi_users: matched users map to their existing D10
 * account, everyone else is created by the mmi_users migration.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/mmi_user_audit.php
 * CSV report lands in scripts-dev/mmi_user_reconciliation.csv (untracked).
 */

use Drupal\Core\Database\Database;

$mmi = Database::getConnection('default', 'migrate_mmi');
$d10 = Database::getConnection();

// MMI D7 users with their CAS name where one exists.
$users = $mmi->query("SELECT u.uid, u.name, u.mail, u.status, u.login, c.cas_name
  FROM {users} u LEFT JOIN {cas_user} c ON c.uid = u.uid WHERE u.uid > 0 ORDER BY u.uid")->fetchAllAssoc('uid');

// Referenced uids: node authors, revision authors, OG members.
$ref = [];
foreach ($mmi->query("SELECT DISTINCT uid FROM {node}") as $r) $ref[$r->uid] = 1;
foreach ($mmi->query("SELECT DISTINCT uid FROM {node_revision}") as $r) $ref[$r->uid] = 1;
if ($mmi->schema()->tableExists('og_membership')) {
  foreach ($mmi->query("SELECT DISTINCT etid FROM {og_membership} WHERE entity_type='user'") as $r) $ref[$r->etid] = 1;
}
unset($ref[0]);

// D10 lookup tables.
$authmap = [];
foreach ($d10->query("SELECT LOWER(authname) an, uid FROM {authmap} WHERE provider='cas'") as $r) $authmap[$r->an] = $r->uid;
$d10mail = [];
foreach ($d10->query("SELECT LOWER(mail) m, uid FROM {users_field_data} WHERE uid > 0 AND mail IS NOT NULL AND mail <> ''") as $r) $d10mail[$r->m] = $r->uid;
$d10name = [];
foreach ($d10->query("SELECT LOWER(name) n, uid FROM {users_field_data} WHERE uid > 0") as $r) $d10name[$r->n] = $r->uid;

$rows = [];
$counts = ['cas_name' => 0, 'mail' => 0, 'mail_local' => 0, 'unmatched' => 0];
foreach ($users as $uid => $u) {
  $cas = strtolower(trim((string) $u->cas_name));
  $mail = strtolower(trim((string) $u->mail));
  $local = $mail && str_contains($mail, '@') ? explode('@', $mail)[0] : '';
  $via = ''; $target = NULL;

  if ($cas && isset($authmap[$cas])) { $via = 'cas_name'; $target = $authmap[$cas]; }
  elseif ($mail && isset($d10mail[$mail])) { $via = 'mail'; $target = $d10mail[$mail]; }
  elseif ($local && (isset($authmap[$local]) || isset($d10name[$local]))) {
    $via = 'mail_local'; $target = $authmap[$local] ?? $d10name[$local];
  }
  else { $via = 'unmatched'; }
  $counts[$via]++;

  $rows[] = [
    'd7_uid' => $uid,
    'd7_name' => $u->name,
    'cas_name' => $u->cas_name,
    'mail' => $u->mail,
    'status' => $u->status,
    'referenced' => isset($ref[$uid]) ? 1 : 0,
    'match_via' => $via,
    'd10_uid' => $target,
    'd10_name' => $target ? array_search($target, $d10name) : '',
  ];
}

$out = fopen('/var/www/html/scripts-dev/mmi_user_reconciliation.csv', 'w');
fputcsv($out, array_keys($rows[0]));
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);

printf("mmi users: %d (referenced by content/og: %d)\n", count($users), count($ref));
foreach ($counts as $via => $n) printf("  %-10s %d\n", $via, $n);
$unref = array_filter($rows, fn($r) => $r['match_via'] === 'unmatched' && !$r['referenced']);
printf("unmatched AND unreferenced (skippable): %d\n", count($unref));
$conflict = array_filter($rows, fn($r) => $r['match_via'] !== 'unmatched' && isset($users[$r['d10_uid']]) && $r['d10_uid'] != $r['d7_uid']);
printf("matches landing on a uid that is also an MMI d7 uid (cross-wire check): %d\n", count($conflict));
print "CSV: scripts-dev/mmi_user_reconciliation.csv\n";

// --- supplementary signals ---
$wf = $mmi->schema()->tableExists('webform_submissions')
  ? $mmi->query("SELECT COUNT(DISTINCT uid) FROM {webform_submissions} WHERE uid > 0")->fetchField() : 'n/a';
print "distinct webform submitter uids: $wf\n";
if ($wf !== 'n/a') {
  $extra = $mmi->query("SELECT DISTINCT s.uid FROM {webform_submissions} s WHERE s.uid > 0")->fetchCol();
  $newref = array_diff($extra, array_keys($ref));
  print "  submitter uids not already referenced: " . implode(',', $newref) . "\n";
}
$cm = $mmi->schema()->tableExists('comment')
  ? $mmi->query("SELECT GROUP_CONCAT(DISTINCT uid) FROM {comment} WHERE uid > 0")->fetchField() : 'n/a';
print "comment author uids: " . ($cm ?: 'none') . "\n";
$noCas = array_filter($rows, fn($r) => $r['match_via'] === 'unmatched' && !trim((string) $r['cas_name']));
printf("unmatched users with NO cas_name (no ONID on record): %d\n", count($noCas));
$nameClash = array_filter($rows, fn($r) => $r['match_via'] === 'unmatched' && isset($d10name[strtolower(trim($r['d7_name']))]));
printf("unmatched whose NAME collides with an existing D10 username: %d\n", count($nameClash));
foreach ($nameClash as $r) printf("  d7 %d '%s' vs d10 uid %d\n", $r['d7_uid'], $r['d7_name'], $d10name[strtolower(trim($r['d7_name']))]);
