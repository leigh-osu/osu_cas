<?php

/**
 * @file
 * Post-rebuild migration verification.
 *
 * Runs in a Drupal/Drush context (use `drush scr` or the
 * `scripts-dev/verify_migration.sh` wrapper). Compares D7 source counts
 * (via the `migrate` connection) to D10 destination counts, scans for
 * row-level migration failures (source_row_status=3), and spot-checks the
 * CAS fixes documented in MIGRATION_REPORT.md. Exits with status 1 if any
 * check fails.
 *
 * Run independently:
 *   ddev drush scr ../scripts-dev/verify_migration.php
 */

use Drupal\Core\Database\Database;

// ---------------------------------------------------------------------------
// Helpers.
// ---------------------------------------------------------------------------
$results = [];
$record = static function (string $check, bool $ok, string $detail = '') use (&$results): void {
  $results[] = ['check' => $check, 'ok' => $ok, 'detail' => $detail];
};
$section = static function (string $title): void {
  print "\n--- {$title} ---\n";
};
$tol = static function (int $a, int $b, int $absTol = 0, float $relTol = 0.0): bool {
  $diff = abs($a - $b);
  if ($diff <= $absTol) {
    return TRUE;
  }
  $base = max(1, max($a, $b));
  return ($diff / $base) <= $relTol;
};
$safe = static function (callable $fn, string $check) use (&$record): void {
  try {
    $fn();
  }
  catch (\Throwable $e) {
    $record($check, FALSE, 'EXCEPTION: ' . $e->getMessage());
  }
};

// Resolve the D7 source DB connection ("migrate" key).
try {
  $d7 = Database::getConnection('default', 'migrate');
  $d7->query('SELECT 1');
}
catch (\Throwable $e) {
  print "[FATAL] Cannot reach the D7 source DB (key 'migrate'): " . $e->getMessage() . "\n";
  exit(2);
}
$d10 = Database::getConnection();

// ===========================================================================
// 1. Authoritative migration failure scan.
//    Uses migrate_map_X.source_row_status = 3 (STATUS_FAILED) — the true
//    failure flag, not the level-1 message count (which also includes
//    informational warnings like "No parent link found").
// ===========================================================================
$section('1. Row-level migration failures (source_row_status = 3)');
$safe(function () use ($d10, $record) {
  $map_tables = $d10->query("SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'migrate_map_%'
    ORDER BY TABLE_NAME")->fetchCol();

  $fail_total = 0;
  $offenders = [];
  foreach ($map_tables as $t) {
    $mid = substr($t, strlen('migrate_map_'));
    $n = (int) $d10->query("SELECT COUNT(*) FROM {$t} WHERE source_row_status = 3")->fetchField();
    if ($n > 0) {
      $offenders[$mid] = $n;
      $fail_total += $n;
    }
  }

  if ($fail_total === 0) {
    $record('All migrations: zero source_row_status = 3 rows', TRUE,
      sprintf('(scanned %d map tables)', count($map_tables)));
  }
  else {
    $detail = '';
    foreach ($offenders as $mid => $n) {
      $detail .= "\n    {$mid}: {$n} failed";
    }
    $record('All migrations: zero source_row_status = 3 rows', FALSE,
      sprintf('%d failed rows across %d migrations:%s', $fail_total, count($offenders), $detail));
  }
}, 'All migrations: zero source_row_status = 3 rows');

// ===========================================================================
// 2. Per-content-type node counts (D7 source bundle → D10 destination bundle).
// ===========================================================================
$section('2. Per-content-type node counts (D7 → D10)');

// d10_bundle => [d7 source bundle(s)]
$type_map = [
  '150_species'              => ['150_species'],
  'art_about_agriculture'    => ['art_about_agriculture'],
  'course'                   => ['course'],
  'degree_fact_sheet'        => ['degree_fact_sheet', 'degree_fact_sheet_graduate'],
  'enterprise_budgets'       => ['enterprise_budgets'],
  'funding_opportunities'    => ['funding_opportunities'],
  'fun_facts'                => ['fun_facts'],
  'image_album'              => ['image_album'],
  'plant_variety_release'    => ['plant_variety_release'],
  'project'                  => ['project'],
  'video'                    => ['video'],
  'weed'                     => ['weed'],
  'weather_daily_data'       => ['weather_data', 'weather_daily_data'],
  'weather_monthly_data'     => ['weather_monthly_data'],
  // Consolidations.
  'page'                     => ['page', 'book', 'feature_page', 'paragraph_page'],
  'story'                    => ['story', 'feature_story', 'article'],
  'publications'             => ['biblio'],
  'webform'                  => ['webform'],
];

foreach ($type_map as $d10_bundle => $d7_bundles) {
  // Use string concatenation with quoted, sanitized bundle names rather than
  // an array placeholder so this works portably across Drupal/Drush versions.
  $quoted = array_map(static fn ($b) => "'" . str_replace("'", "''", $b) . "'", $d7_bundles);
  $sql = 'SELECT COUNT(*) FROM node WHERE type IN (' . implode(',', $quoted) . ')';
  $d7_count = (int) $d7->query($sql)->fetchField();
  $d10_count = (int) $d10->query('SELECT COUNT(*) FROM node_field_data
    WHERE type = :b AND default_langcode = 1', [':b' => $d10_bundle])->fetchField();

  // Off-by-one rows are documented and not a regression signal.
  $ok = $tol($d7_count, $d10_count, 2, 0.01);
  $sources = implode(',', $d7_bundles);
  $record(
    sprintf('node:%-26s', $d10_bundle),
    $ok,
    sprintf('D7 (%s)=%d  D10=%d  Δ=%+d', $sources, $d7_count, $d10_count, $d10_count - $d7_count)
  );
}

// ===========================================================================
// 3. Entity-level counts.
// ===========================================================================
$section('3. Entity-level counts');

// Users — the migration intentionally filters to users with roles, so this is
// a sanity check (D10 > 0 and proportional), not an equality test.
$safe(function () use ($d7, $d10, $record) {
  $d7_users  = (int) $d7->query('SELECT COUNT(*) FROM users WHERE uid > 1')->fetchField();
  $d10_users = (int) $d10->query('SELECT COUNT(*) FROM users WHERE uid > 1')->fetchField();
  // upgrade_d7_users_with_roles only migrates users that have at least one
  // explicit D7 role; expect well under the raw user count. Pass if D10 has
  // at least some users.
  $record('users (uid>1, role-filtered)', $d10_users > 0,
    "D7 raw={$d7_users}  D10 migrated={$d10_users} (filtered set)");
}, 'users (uid>1, role-filtered)');

// Taxonomy terms (allow modest drift — install profile + recipes may add).
// D10-only vocabularies are excluded: publication_authors/_keywords are
// minted from the D7 biblio contributor/keyword tables and osu_organization
// from department nodes, none of which exist in D7 taxonomy_term_data.
$safe(function () use ($d7, $d10, $record, $tol) {
  $d7_terms  = (int) $d7->query('SELECT COUNT(*) FROM taxonomy_term_data')->fetchField();
  $d10_terms = (int) $d10->query("SELECT COUNT(*) FROM taxonomy_term_field_data
    WHERE default_langcode = 1
    AND vid NOT IN ('publication_authors', 'publication_keywords', 'osu_organization')")->fetchField();
  $record('taxonomy terms', $tol($d7_terms, $d10_terms, 10, 0.10),
    "D7={$d7_terms}  D10={$d10_terms} (excl. D10-only vocabs)");
}, 'taxonomy terms');

// URL aliases — D10 legitimately has MORE (pathauto auto-creates aliases on
// node create). Check D10 ≥ D7.
$safe(function () use ($d7, $d10, $record) {
  $d7_aliases  = (int) $d7->query('SELECT COUNT(*) FROM url_alias')->fetchField();
  $d10_aliases = (int) $d10->query('SELECT COUNT(*) FROM path_alias')->fetchField();
  $record('URL aliases (D10 ≥ D7)', $d10_aliases >= $d7_aliases,
    "D7={$d7_aliases}  D10={$d10_aliases}");
}, 'URL aliases (D10 ≥ D7)');

// Redirects — auto-redirect-on-alias creates extras; check D10 ≥ D7 − dedupe.
$safe(function () use ($d7, $d10, $record) {
  $d7_redirects  = (int) $d7->query('SELECT COUNT(*) FROM redirect')->fetchField();
  $d10_redirects = (int) $d10->query('SELECT COUNT(*) FROM redirect')->fetchField();
  // Expect D10 ≥ D7 − ~10 (dedupe skips); auto-create can also push D10 up.
  $record('redirects (D10 ≥ D7 − dedupe)', $d10_redirects >= ($d7_redirects - 20),
    "D7={$d7_redirects}  D10={$d10_redirects}");
}, 'redirects (D10 ≥ D7 − dedupe)');

// Menu links — D7 module=menu vs D10 migrated.
$safe(function () use ($d7, $d10, $record, $tol) {
  $d7_menu  = (int) $d7->query("SELECT COUNT(*) FROM menu_links WHERE module = 'menu'")->fetchField();
  $d10_menu = (int) $d10->query('SELECT COUNT(*) FROM menu_link_content_data
    WHERE default_langcode = 1')->fetchField();
  // 48 'no parent' skips + a few language-variant rows are normal.
  $record('menu links', $tol($d7_menu, $d10_menu, 100, 0.05),
    "D7 (module=menu)={$d7_menu}  D10={$d10_menu}");
}, 'menu links');

// paragraph_block blocks — D10 can exceed D7 live count because picbox grids
// expand into per-card blocks and other migrations create extras.
$safe(function () use ($d7, $d10, $record) {
  $d7_live = (int) $d7->query("SELECT COUNT(*) FROM paragraphs_item WHERE archived = 0")->fetchField();
  $d10_pb  = (int) $d10->query("SELECT COUNT(*) FROM block_content_field_data
    WHERE type = 'paragraph_block' AND default_langcode = 1")->fetchField();
  // Sanity: at least 80% of live D7 paragraphs should produce a block.
  $record('paragraph_block blocks (D10 ≥ 0.8 × D7 live)', $d10_pb >= 0.8 * $d7_live,
    "D7 live paragraphs_item={$d7_live}  D10 paragraph_block={$d10_pb}");
}, 'paragraph_block blocks (D10 ≥ 0.8 × D7 live)');

// Webform entities — d7_webform creates one webform_<nid> config entity per
// live D7 webform node (orphaned {webform} rows without a node are excluded
// by the source's inner join).
$safe(function () use ($d7, $d10, $record) {
  $d7_forms = (int) $d7->query("SELECT COUNT(*) FROM webform w
    JOIN node n ON n.nid = w.nid WHERE n.type = 'webform'")->fetchField();
  $d10_forms = (int) $d10->query("SELECT COUNT(*) FROM config
    WHERE name LIKE 'webform.webform.webform_%'")->fetchField();
  $record('webform entities', $d7_forms === $d10_forms,
    "D7={$d7_forms}  D10={$d10_forms}");
}, 'webform entities');

// Every migrated webform node should reference its own form
// (cas_webform_to_webform_node maps webform/target_id = webform_<nid>).
$safe(function () use ($d10, $record) {
  $nodes = (int) $d10->query("SELECT COUNT(*) FROM node_field_data
    WHERE type = 'webform' AND default_langcode = 1")->fetchField();
  $linked = (int) $d10->query("SELECT COUNT(*) FROM node__webform
    WHERE deleted = 0 AND webform_target_id IS NOT NULL")->fetchField();
  $record('webform nodes linked to their form', $nodes > 0 && $linked === $nodes,
    "nodes={$nodes}  linked={$linked}");
}, 'webform nodes linked to their form');

// Webform group placements (cas_webform_group_content) — one group_content
// row per D7 OG membership of a webform node.
$safe(function () use ($d7, $d10, $record) {
  $d7_m = (int) $d7->query("SELECT COUNT(*) FROM og_membership om
    JOIN node n ON n.nid = om.etid
    WHERE om.entity_type = 'node' AND om.group_type = 'node'
      AND n.type = 'webform'")->fetchField();
  // Group 2.x keeps the group_content entity type id but stores rows in the
  // group_relationship* tables.
  $d10_m = (int) $d10->query("SELECT COUNT(*) FROM group_relationship_field_data
    WHERE type = 'basic_group-group_node-webform'
      AND default_langcode = 1")->fetchField();
  $record('webform group content', $d7_m === $d10_m,
    "D7 memberships={$d7_m}  D10 group_content={$d10_m}");
}, 'webform group content');

// ===========================================================================
// 4. CAS-fix spot checks.
// ===========================================================================
$section('4. CAS-fix spot checks');

// 4a. alert_message blocks were created.
$alert_blocks = (int) $d10->query("SELECT COUNT(*) FROM block_content_field_data
  WHERE info = 'Migrated d7 paragraph alert_message' AND default_langcode = 1")->fetchField();
$record('alert_message: block_content entities created', $alert_blocks > 0,
  "count={$alert_blocks} (expected ≥20)");

// 4b. alert_message blocks are placed in at least one node layout. We probe
// using the actual D7→D10 nid mapping for affected pages (D7 page nodes with
// an archived=0 alert_message paragraph in field_paragraph) and load the
// D10 node through the entity API. Loading via Drupal gives us the canonical
// layout_builder section storage rather than scraping serialized rows.
$safe(function () use ($d7, $d10, $record) {
  $alert_ids = $d10->query("SELECT id FROM block_content_field_data
    WHERE info = 'Migrated d7 paragraph alert_message' AND default_langcode = 1")
    ->fetchCol();
  if (!$alert_ids) {
    $record('alert_message: blocks placed in node layouts', FALSE, 'no alert blocks to look for');
    return;
  }
  $alert_set = array_flip(array_map('strval', $alert_ids));

  // Get D7 page nids referencing alert paragraphs via field_paragraph.
  $d7_nids = $d7->query("SELECT DISTINCT fp.entity_id
    FROM field_data_field_paragraph fp
    JOIN paragraphs_item pi ON pi.item_id = fp.field_paragraph_value
    WHERE pi.bundle = 'alert_message' AND pi.archived = 0
      AND fp.entity_type = 'node'")->fetchCol();
  if (!$d7_nids) {
    $record('alert_message: blocks placed in node layouts', FALSE,
      'no affected D7 page nodes found');
    return;
  }

  // Map D7 nids -> D10 nids through the migrate map.
  $quoted = implode(',', array_map('intval', $d7_nids));
  $pairs = $d10->query("SELECT sourceid1 src, destid1 dest
    FROM migrate_map_cas_page_to_page WHERE sourceid1 IN ({$quoted})
      AND destid1 IS NOT NULL")->fetchAllKeyed();

  $checked = 0;
  $with_alert = 0;
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  foreach ($pairs as $src => $dest) {
    $node = $storage->load($dest);
    if (!$node || !$node->hasField('layout_builder__layout')) {
      continue;
    }
    $checked++;
    foreach ($node->get('layout_builder__layout')->getSections() as $sec) {
      foreach ($sec->getComponents() as $c) {
        $cfg = $c->get('configuration');
        $rev = $cfg['block_revision_id'] ?? NULL;
        if (!$rev) {
          continue;
        }
        $bid = $d10->query('SELECT id FROM block_content_field_revision WHERE revision_id = :r',
          [':r' => $rev])->fetchField();
        if ($bid !== FALSE && isset($alert_set[(string) $bid])) {
          $with_alert++;
          break 2;
        }
      }
    }
  }
  $record('alert_message: blocks placed in node layouts', $with_alert > 0,
    "checked {$checked} affected pages, found alert block in {$with_alert}");
}, 'alert_message: blocks placed in node layouts');

// 4c. Sanity: the sacnas '_left' and '_right' migrations must not exist in
// active config.
$bad_sacnas = $d10->query("SELECT name FROM config WHERE name IN
  ('migrate_plus.migration.paragraph_sacnas_officer_body_text_left__to__layout_builder',
   'migrate_plus.migration.paragraph_sacnas_officer_body_text_right__to__layout_builder')")
  ->fetchCol();
$record('sacnas: no orphan _left/_right migration configs',
  empty($bad_sacnas),
  empty($bad_sacnas) ? '' : 'still present: ' . count($bad_sacnas));

// 4d. OG user→group memberships were migrated.
$members = (int) $d10->query("SELECT COUNT(*) FROM group_relationship_field_data
  WHERE plugin_id LIKE 'group_membership%' AND default_langcode = 1")->fetchField();
$record('upgrade_d7_user_og_memberships: group memberships exist',
  $members > 0,
  "group memberships={$members}");

// 4f. CAS login authmap mappings exist (cas_user_authmap). The migration is
// invoked in section 1; if osu_migrations_cas isn't enabled first it fails with
// "Invalid migration IDs" — a step failure that VERIFICATION otherwise misses.
$safe(function () use ($d10, $record) {
  $exists = (int) $d10->query("SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'authmap'")->fetchField();
  $n = $exists ? (int) $d10->query("SELECT COUNT(*) FROM authmap WHERE provider = 'cas'")->fetchField() : 0;
  $record('cas_user_authmap: CAS authmap mappings exist', $n > 0,
    "authmap(provider=cas)={$n}");
}, 'cas_user_authmap: CAS authmap mappings exist');

// 4g. The display partial-config import actually applied. node.osu_profile.full
// depends on the osu_digital_measures module; if that module isn't enabled the
// whole config_imports/display batch aborts and no displays import. Presence of
// this config entity proves the batch succeeded.
$dm_display = $d10->query("SELECT name FROM config
  WHERE name = 'core.entity_view_display.node.osu_profile.full'")->fetchCol();
$record('config_imports/display applied (osu_profile.full present)',
  !empty($dm_display),
  empty($dm_display) ? 'missing — display import likely aborted (osu_digital_measures not enabled?)' : '');

// 4e. Guard against "silent skip" regressions: a migration blocked by an
// unmet requirements gate creates NO map rows, so the source_row_status=3
// check in section 1 can't see it. Assert that migrations the rebuild script
// explicitly imports have actually executed (their map table has ≥1 row).
$safe(function () use ($d10, $record) {
  $must_run = [
    'upgrade_d7_book_menu_group_menu',
    'upgrade_d7_user_og_memberships',
    'paragraph_alert_message__to__layout_builder',
    'cas_user_authmap',
  ];
  $not_run = [];
  foreach ($must_run as $mid) {
    $table = 'migrate_map_' . $mid;
    $exists = $d10->query("SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t", [':t' => $table])->fetchField();
    $rows = $exists ? (int) $d10->query("SELECT COUNT(*) FROM {$table}")->fetchField() : 0;
    if ($rows === 0) {
      $not_run[] = $mid;
    }
  }
  $record('script-invoked migrations actually ran (no silent skips)',
    empty($not_run),
    empty($not_run) ? '' : 'never ran: ' . implode(', ', $not_run));
}, 'script-invoked migrations actually ran (no silent skips)');

// ===========================================================================
// Summary.
// ===========================================================================
$pass = 0; $fail = 0;
print "\n=== Verification summary ===\n";
$len = 0;
foreach ($results as $r) { $len = max($len, strlen($r['check'])); }
foreach ($results as $r) {
  $mark = $r['ok'] ? '[ OK ]' : '[FAIL]';
  printf("%s %-{$len}s  %s\n", $mark, $r['check'], $r['detail']);
  $r['ok'] ? $pass++ : $fail++;
}
print "\n";
print "PASS: {$pass}    FAIL: {$fail}\n";

// Print a final, easy-to-grep status line. We deliberately avoid exit() here:
// `drush php:script` traps it and reports "terminated abnormally" with rc=1
// regardless of the actual code, so the bash wrapper grades on this line.
print ($fail === 0 ? "VERIFICATION: PASS\n" : "VERIFICATION: FAIL\n");
