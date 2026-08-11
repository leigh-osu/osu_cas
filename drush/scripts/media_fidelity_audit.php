<?php

/**
 * @file
 * Audits every migrated node for media that D7 showed and D10 does not.
 *
 * Three defects found by spot-checking single nodes (a 2-col background image
 * swallowed by a colour style, media-token links dropped with their
 * external_url, hardcoded file URLs left unrewritten) all shared a shape: the
 * page still looked deliberate, so nothing flagged them. This walks every
 * node instead.
 *
 * Comparison is by FILE, not markup. A D7 media token, an <img src>, a file
 * field and a 2-col background all reduce to the basename of the file they
 * point at; the same is done on the D10 side for drupal-media embeds, media
 * reference fields and Layout Builder background settings. Paths changed in
 * the migration (site directory rename, year subdirectories), so matching on
 * URL would report thousands of false differences.
 *
 * Links are audited separately: a D7 media token carrying an external_url, or
 * an <a> wrapping an image, should still be a link in D10. That is what went
 * unnoticed on node 284766, where nine fact-sheet PDFs became plain images.
 *
 * Deliberate exclusions, so the report is signal rather than noise:
 * - D7 node types the project does not migrate (see CLAUDE.md).
 * - Paragraph bundles the layout migration ignores: viewfield and
 *   2_column_views, which are views embeds with no D10 equivalent.
 * - Nodes with no D10 counterpart at all (never migrated).
 *
 * Usage:
 *   ddev drush --uri=https://osu-cas.ddev.site scr \
 *     drush/scripts/media_fidelity_audit.php
 *
 * Writes (untracked, in scripts-dev/):
 *   media_fidelity_audit.md   — summary, worst offenders, breakdown
 *   media_fidelity_audit.csv  — one row per node with a discrepancy
 */

use Drupal\Core\Database\Database;

$md_path = DRUPAL_ROOT . '/../scripts-dev/media_fidelity_audit.md';
$csv_path = DRUPAL_ROOT . '/../scripts-dev/media_fidelity_audit.csv';

$d7 = Database::getConnection('default', 'migrate');
$db = \Drupal::database();

// D7 content types that are not migrated at all.
const SKIPPED_TYPES = [
  'announcement', 'degree', 'faq', 'highlight', 'multi_menu',
  'navigation_grid', 'poster', 'sidebar_carousel', 'simple_tab',
  'slide_show', 'stylesheet_overlay', 'feed',
];
// Paragraph bundles the layout migration deliberately drops.
const SKIPPED_PARAGRAPH_BUNDLES = ['viewfield', '2_column_views'];

/**
 * Finds the *_value text tables that actually contain media or file refs.
 *
 * Querying every field_data_* table per node would be thousands of queries a
 * row; almost all of them never hold media.
 */
function cas_audit_text_tables($connection, string $prefix): array {
  $out = [];
  $tables = $connection->query("SELECT DISTINCT table_name AS tname
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name LIKE '" . $prefix . "%'
      AND column_name LIKE '%\_value'")->fetchCol();
  foreach ($tables as $table) {
    $column = NULL;
    foreach ($connection->query('SHOW COLUMNS FROM {' . $table . '}') as $c) {
      if (str_ends_with($c->Field, '_value')) {
        $column = $c->Field;
        break;
      }
    }
    if (!$column) {
      continue;
    }
    try {
      $hit = $connection->query('SELECT 1 FROM {' . $table . '}
        WHERE ' . $column . ' LIKE :a OR ' . $column . ' LIKE :b OR ' . $column . ' LIKE :c
        LIMIT 1', [':a' => '%[[{%', ':b' => '%/files/%', ':c' => '%<drupal-media%'])->fetchField();
    }
    catch (\Exception $e) {
      continue;
    }
    if ($hit) {
      $out[$table] = $column;
    }
  }
  return $out;
}

/**
 * Whether a URL points at a file this audit cares about.
 *
 * Link fields hold plenty of ordinary web links; treating "the path has an
 * extension" as file-ish counted .html, .aspx, .jsp and .edu addresses as
 * missing media. A link is only in scope if it targets a files directory or
 * carries a document/image extension.
 */
function cas_audit_file_url(?string $url): ?string {
  if (!is_string($url) || $url === '') {
    return NULL;
  }
  $path = parse_url(preg_replace('~^(internal|base|entity):~', '', $url), PHP_URL_PATH);
  if (!$path) {
    return NULL;
  }
  $is_file = str_contains($path, '/files/')
    || preg_match('~\.(pdf|docx?|xlsx?|pptx?|jpe?g|png|gif|svg|mp4|mov|zip|csv|txt|rtf)$~i', $path);
  return $is_file ? strtolower(basename(rawurldecode($path))) : NULL;
}

// Index every basename on the D7 filesystem. D7 markup references plenty of
// files that were already broken there -- node 4774 alone links 65 thumbnails
// under main/aaa/artwork/thumb/, a directory that does not exist on D7 at all,
// and strip_dead_legacy_refs.php removes them during the rebuild. Those are
// not migration losses and must not be reported as such, so a D7 reference
// only counts if its file was actually present to begin with.
print "indexing the D7 files tree...\n";
$d7_files_root = rtrim(\Drupal\Core\Site\Settings::get(
  'cas_migrate_d7_files_path', '/var/www/d7/sites/agscid7/files'), '/');
$d7_on_disk = [];
if (is_dir($d7_files_root)) {
  $iterator = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($d7_files_root,
      \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME));
  foreach ($iterator as $pathname) {
    $d7_on_disk[strtolower(basename($pathname))] = TRUE;
  }
}
printf("  D7 files on disk: %d distinct basenames%s\n", count($d7_on_disk),
  $d7_on_disk ? '' : ' (tree not mounted -- existence check disabled)');

print "indexing text fields that hold media...\n";
$d7_tables = cas_audit_text_tables($d7, 'field_data_');
$d10_tables = cas_audit_text_tables($db, 'node__') + cas_audit_text_tables($db, 'block_content__')
  + cas_audit_text_tables($db, 'paragraph__');
printf("  D7: %d tables, D10: %d tables\n", count($d7_tables), count($d10_tables));

// fid -> basename maps, both sides, loaded once.
$d7_file = [];
foreach ($d7->query('SELECT fid, uri FROM {file_managed}') as $r) {
  $d7_file[(int) $r->fid] = strtolower(basename($r->uri));
}
$d10_file = [];
foreach ($db->query('SELECT fid, uri FROM {file_managed}') as $r) {
  $d10_file[(int) $r->fid] = strtolower(basename($r->uri));
}
// D10 media -> basename, by id and by uuid (embeds reference the uuid).
// Media source fields are discovered rather than assumed: the bundles here
// use field_media_image and field_media_file, and a hardcoded guess at
// field_media_document simply fataled.
$media_uuid = [];
foreach ($db->query('SELECT mid, uuid FROM {media}') as $r) {
  $media_uuid[(int) $r->mid] = $r->uuid;
}
$media_by_id = [];
$media_by_uuid = [];
$media_tables = $db->query("SELECT DISTINCT table_name AS tname FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name LIKE 'media\_\_%'
    AND column_name LIKE '%\_target\_id'")->fetchCol();
foreach ($media_tables as $table) {
  $column = NULL;
  foreach ($db->query('SHOW COLUMNS FROM {' . $table . '}') as $c) {
    if (str_ends_with($c->Field, '_target_id')) {
      $column = $c->Field;
      break;
    }
  }
  if (!$column) {
    continue;
  }
  foreach ($db->query('SELECT entity_id, ' . $column . ' AS fid FROM {' . $table . '}') as $r) {
    $name = $d10_file[(int) $r->fid] ?? NULL;
    if ($name === NULL) {
      continue;
    }
    $media_by_id[(int) $r->entity_id] = $name;
    if (isset($media_uuid[(int) $r->entity_id])) {
      $media_by_uuid[$media_uuid[(int) $r->entity_id]] = $name;
    }
  }
}
printf("  files: D7 %d, D10 %d; media resolved: %d\n", count($d7_file), count($d10_file), count($media_by_id));

/**
 * Extracts referenced file basenames, and linked ones, from D7 markup.
 */
function cas_audit_scan_d7(string $text, array $d7_file): array {
  $media = [];
  $linked = [];
  // Media tokens: [[{"fid":"123", ... "external_url":"..."}]]
  if (preg_match_all('~\[\[\s*(\{.+?\})\s*\]\]~s', $text, $m)) {
    foreach ($m[1] as $json) {
      $tag = json_decode(preg_replace('/\s+/', ' ', $json), TRUE);
      if (!is_array($tag) || empty($tag['fid'])) {
        continue;
      }
      $name = $d7_file[(int) $tag['fid']] ?? NULL;
      if ($name === NULL) {
        continue;
      }
      $media[$name] = TRUE;
      $ext = $tag['fields']['external_url'] ?? NULL;
      if (is_string($ext) && trim($ext) !== '') {
        $linked[$name] = TRUE;
      }
    }
  }
  // Hardcoded <img src> and <a href> to files.
  if (preg_match_all('~<img[^>]+src="([^"]*/files/[^"?#]+)~i', $text, $m)) {
    foreach ($m[1] as $u) {
      $media[strtolower(basename(rawurldecode($u)))] = TRUE;
    }
  }
  if (preg_match_all('~<a[^>]+href="([^"]*/files/[^"?#]+)~i', $text, $m)) {
    foreach ($m[1] as $u) {
      $linked[strtolower(basename(rawurldecode($u)))] = TRUE;
    }
  }
  return [$media, $linked];
}

/**
 * Extracts referenced file basenames, and linked ones, from D10 markup.
 */
function cas_audit_scan_d10(string $text, array $media_by_uuid): array {
  $media = [];
  $linked = [];
  if (preg_match_all('~<drupal-media[^>]+data-entity-uuid="([^"]+)"~i', $text, $m)) {
    foreach ($m[1] as $uuid) {
      if (isset($media_by_uuid[$uuid])) {
        $media[$media_by_uuid[$uuid]] = TRUE;
      }
    }
  }
  if (preg_match_all('~<img[^>]+src="([^"]*/files/[^"?#]+)~i', $text, $m)) {
    foreach ($m[1] as $u) {
      $media[strtolower(basename(rawurldecode($u)))] = TRUE;
    }
  }
  // A link wrapping an embed, or any link to a file.
  if (preg_match_all('~<a[^>]+href="([^"]*/files/[^"?#]+)~i', $text, $m)) {
    foreach ($m[1] as $u) {
      $linked[strtolower(basename(rawurldecode($u)))] = TRUE;
    }
  }
  // <a ...><drupal-media uuid> — the link target is the media itself.
  if (preg_match_all('~<a[^>]*>\s*<drupal-media[^>]+data-entity-uuid="([^"]+)"~i', $text, $m)) {
    foreach ($m[1] as $uuid) {
      if (isset($media_by_uuid[$uuid])) {
        $linked[$media_by_uuid[$uuid]] = TRUE;
      }
    }
  }
  return [$media, $linked];
}

// D7 paragraph/field-collection items per node, and their bundles.
print "mapping D7 paragraphs...\n";
$d7_items = [];
foreach (['field_paragraph', 'field_larch_paragraph_sections', 'field_paragraphs'] as $field) {
  $table = 'field_data_' . $field;
  if (!$d7->query('SHOW TABLES LIKE :t', [':t' => $table])->fetchField()) {
    continue;
  }
  foreach ($d7->query('SELECT entity_id, ' . $field . '_value AS v FROM {' . $table . '}
    WHERE entity_type = :e', [':e' => 'node']) as $r) {
    $d7_items[(int) $r->entity_id][] = (int) $r->v;
  }
}
$item_bundle = [];
foreach ($d7->query('SELECT item_id, bundle FROM {paragraphs_item}') as $r) {
  $item_bundle[(int) $r->item_id] = $r->bundle;
}
// Field collections hanging off those paragraphs (picbox cards, columns).
// These must be resolved through their attachment field, never by reusing the
// paragraph IDs: paragraphs_item and field_collection_item are separate D7
// tables with independent sequences that share 22,530 item_ids, so treating a
// paragraph ID as a field-collection ID silently pulls in another node's
// content. That is what put budget PDFs on a page about blackberries.
print "mapping D7 field collections...\n";
$fc_children = [];
$fc_fields = $d7->query('SELECT DISTINCT field_name FROM {field_collection_item}')->fetchCol();
foreach ($fc_fields as $field) {
  $table = 'field_data_' . $field;
  if (!$d7->query('SHOW TABLES LIKE :t', [':t' => $table])->fetchField()) {
    continue;
  }
  try {
    $rows = $d7->query('SELECT entity_type, entity_id, ' . $field . '_value AS v FROM {' . $table . '}');
  }
  catch (\Exception $e) {
    continue;
  }
  foreach ($rows as $r) {
    if ($r->v) {
      $fc_children[$r->entity_type][(int) $r->entity_id][] = (int) $r->v;
    }
  }
}
printf("  field-collection fields: %d\n", count($fc_fields));

// D10: which inline blocks and paragraphs belong to each node.
print "mapping D10 layout blocks...\n";
$node_blocks = [];
foreach ($db->query('SELECT entity_id, layout_builder__layout_section FROM {node__layout_builder__layout}') as $r) {
  if (!$r->layout_builder__layout_section) {
    continue;
  }
  if (preg_match_all('~s:17:"block_revision_id";(?:s:\d+:"(\d+)"|i:(\d+))~', $r->layout_builder__layout_section, $m, PREG_SET_ORDER)) {
    foreach ($m as $match) {
      $node_blocks[(int) $r->entity_id][] = (int) ($match[1] !== '' ? $match[1] : $match[2]);
    }
  }
  // Background media referenced from section/component settings.
  if (preg_match_all('~"media_id";s:\d+:"(\d+)"~', $r->layout_builder__layout_section, $m2)) {
    foreach ($m2[1] as $mid) {
      $node_blocks['bg'][(int) $r->entity_id][] = (int) $mid;
    }
  }
}
$block_bg = $node_blocks['bg'] ?? [];
unset($node_blocks['bg']);
$rev_to_block = [];
foreach ($db->query('SELECT revision_id, id FROM {block_content_revision}') as $r) {
  $rev_to_block[(int) $r->revision_id] = (int) $r->id;
}

/**
 * Indexes *_target_id columns once per prefix.
 *
 * Resolving these inside the node loop would be a schema query per node.
 */
function cas_audit_ref_tables($connection, string $prefix): array {
  $out = [];
  $tables = $connection->query("SELECT DISTINCT table_name AS tname FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name LIKE '" . $prefix . "%'
      AND column_name LIKE '%\_target\_id'")->fetchCol();
  foreach ($tables as $table) {
    foreach ($connection->query('SHOW COLUMNS FROM {' . $table . '}') as $c) {
      if (str_ends_with($c->Field, '_target_id')) {
        $out[$table] = $c->Field;
        break;
      }
    }
  }
  return $out;
}
$ref_tables = [
  'node__' => cas_audit_ref_tables($db, 'node__'),
  'block_content__' => cas_audit_ref_tables($db, 'block_content__'),
  'paragraph__' => cas_audit_ref_tables($db, 'paragraph__'),
];

/**
 * Indexes link-field columns, which hold file links outside any markup.
 *
 * Publications keep their PDF in field_pub_url, cards in
 * field_osu_card_link. Auditing only markup reported ~1,300 of those as lost
 * links when they had simply moved from D7 markup into a D10 link field.
 */
function cas_audit_link_tables($connection, string $prefix, string $suffix): array {
  $out = [];
  $rows = $connection->query("SELECT table_name AS tname, column_name AS cname
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name LIKE '" . $prefix . "%'
      AND column_name LIKE '%" . $suffix . "' AND table_name NOT LIKE '%revision%'")->fetchAll();
  foreach ($rows as $r) {
    $out[$r->tname] = $r->cname;
  }
  return $out;
}
$d10_link_tables = [
  'node__' => cas_audit_link_tables($db, 'node__', '\_uri'),
  'block_content__' => cas_audit_link_tables($db, 'block_content__', '\_uri'),
  'paragraph__' => cas_audit_link_tables($db, 'paragraph__', '\_uri'),
];
// D7 link fields store the target in a *_url column.
$d7_link_tables = cas_audit_link_tables($d7, 'field_data_', '\_url');
printf("  link fields: D10 %d, D7 %d\n",
  array_sum(array_map('count', $d10_link_tables)), count($d7_link_tables));

// D10 paragraphs: every paragraph migration lands its content either in an
// inline block or in a paragraph entity referenced from one (accordions,
// vertical tabs, picbox cards). Index which paragraphs hang off which parent
// so their media is audited too -- otherwise a whole class of migrated
// content is silently absent from the comparison.
$paragraph_children = [];
if ($db->schema()->tableExists('paragraphs_item_field_data')
  && $db->schema()->fieldExists('paragraphs_item_field_data', 'parent_id')) {
  foreach ($db->query('SELECT id, parent_type, parent_id FROM {paragraphs_item_field_data}') as $r) {
    if ($r->parent_type && $r->parent_id) {
      $paragraph_children[$r->parent_type][(int) $r->parent_id][] = (int) $r->id;
    }
  }
}
printf("  D10 paragraph parents indexed: %d\n",
  array_sum(array_map('count', $paragraph_children)));

// Nodes to audit: D10 nodes with a D7 counterpart of a migrated type.
$d7_types = [];
foreach ($d7->query('SELECT nid, type FROM {node}') as $r) {
  $d7_types[(int) $r->nid] = $r->type;
}
$nodes = $db->query('SELECT nid, type, title FROM {node_field_data} WHERE default_langcode = 1')->fetchAll();
printf("auditing %d D10 nodes...\n", count($nodes));

$rows = [];
$stats = ['audited' => 0, 'skipped_type' => 0, 'no_d7' => 0,
  'missing_media' => 0, 'missing_links' => 0, 'clean' => 0];
$missing_by_type = [];
// Stories and pages carry the bulk of the editorial media, so they get their
// own line in the report rather than being buried in the type breakdown.
$focus = [];
$audited_by_type = [];

// Progress: this walks tens of thousands of nodes with a few dozen queries
// each, so a silent run is indistinguishable from a hung one.
$started = microtime(TRUE);
$total_nodes = count($nodes);
$seen = 0;

foreach ($nodes as $node) {
  $seen++;
  if ($seen % 2000 === 0) {
    $elapsed = microtime(TRUE) - $started;
    $rate = $elapsed > 0 ? $seen / $elapsed : 0;
    $remaining = $rate > 0 ? ($total_nodes - $seen) / $rate : 0;
    printf("  %6d/%d (%2d%%)  %.0f nodes/s  ~%dm left  [media %d, links %d]\n",
      $seen, $total_nodes, (int) ($seen / max(1, $total_nodes) * 100),
      $rate, (int) round($remaining / 60),
      $stats['missing_media'], $stats['missing_links']);
  }
  $nid = (int) $node->nid;
  $d7_type = $d7_types[$nid] ?? NULL;
  if ($d7_type === NULL) {
    $stats['no_d7']++;
    continue;
  }
  if (in_array($d7_type, SKIPPED_TYPES, TRUE)) {
    $stats['skipped_type']++;
    continue;
  }
  $stats['audited']++;
  $audited_by_type[$node->type] = ($audited_by_type[$node->type] ?? 0) + 1;

  // --- D7 side -------------------------------------------------------------
  $items = $d7_items[$nid] ?? [];
  $items = array_values(array_filter($items, function ($id) use ($item_bundle) {
    return !in_array($item_bundle[$id] ?? '', SKIPPED_PARAGRAPH_BUNDLES, TRUE);
  }));
  $d7_media = [];
  $d7_linked = [];
  $entities = [['node', [$nid]]];
  if ($items) {
    $entities[] = ['paragraphs_item', $items];
  }
  // Real field-collection IDs, resolved through their attachment field. A node
  // can carry them directly (picbox cards on a group page) as well as through
  // its paragraphs, so this runs whether or not there are paragraphs.
  $fc_items = $fc_children['node'][$nid] ?? [];
  foreach ($items as $item) {
    foreach ($fc_children['paragraphs_item'][$item] ?? [] as $child) {
      $fc_items[] = $child;
    }
  }
  if ($fc_items) {
    $entities[] = ['field_collection_item', array_values(array_unique($fc_items))];
  }
  foreach ($d7_tables as $table => $column) {
    foreach ($entities as [$etype, $ids]) {
      try {
        $vals = $d7->query('SELECT ' . $column . ' AS v FROM {' . $table . '}
          WHERE entity_type = :e AND entity_id IN (:i[])', [':e' => $etype, ':i[]' => $ids])->fetchCol();
      }
      catch (\Exception $e) {
        continue;
      }
      foreach ($vals as $v) {
        if (!is_string($v) || $v === '') {
          continue;
        }
        [$m, $l] = cas_audit_scan_d7($v, $d7_file);
        $d7_media += $m;
        $d7_linked += $l;
      }
    }
  }

  // D7 link fields (a file linked from a link widget rather than markup).
  foreach ($d7_link_tables as $table => $column) {
    foreach ($entities as [$etype, $ids]) {
      try {
        $vals = $d7->query('SELECT ' . $column . ' AS v FROM {' . $table . '}
          WHERE entity_type = :e AND entity_id IN (:i[])', [':e' => $etype, ':i[]' => $ids])->fetchCol();
      }
      catch (\Exception $e) {
        continue;
      }
      foreach ($vals as $v) {
        if ($name = cas_audit_file_url($v)) {
          $d7_linked[$name] = TRUE;
        }
      }
    }
  }

  // --- D10 side ------------------------------------------------------------
  $d10_media = [];
  $d10_linked = [];
  $block_ids = [];
  foreach ($node_blocks[$nid] ?? [] as $rev) {
    if (isset($rev_to_block[$rev])) {
      $block_ids[] = $rev_to_block[$rev];
    }
  }
  foreach ($block_bg[$nid] ?? [] as $mid) {
    if (isset($media_by_id[$mid])) {
      $d10_media[$media_by_id[$mid]] = TRUE;
    }
  }
  // Paragraphs hanging off the node or any of its inline blocks, plus one
  // level of nesting (an accordion item inside an accordion block).
  $paragraph_ids = $paragraph_children['node'][$nid] ?? [];
  foreach ($block_ids as $bid) {
    foreach ($paragraph_children['block_content'][$bid] ?? [] as $pid) {
      $paragraph_ids[] = $pid;
    }
  }
  foreach ($paragraph_ids as $pid) {
    foreach ($paragraph_children['paragraph'][$pid] ?? [] as $child) {
      $paragraph_ids[] = $child;
    }
  }
  $paragraph_ids = array_values(array_unique($paragraph_ids));

  foreach ($d10_tables as $table => $column) {
    $targets = str_starts_with($table, 'node__') ? [$nid]
      : (str_starts_with($table, 'block_content__') ? $block_ids
        : (str_starts_with($table, 'paragraph__') ? $paragraph_ids : []));
    if (!$targets) {
      continue;
    }
    try {
      $vals = $db->query('SELECT ' . $column . ' AS v FROM {' . $table . '}
        WHERE entity_id IN (:i[])', [':i[]' => $targets])->fetchCol();
    }
    catch (\Exception $e) {
      continue;
    }
    foreach ($vals as $v) {
      if (!is_string($v) || $v === '') {
        continue;
      }
      [$m, $l] = cas_audit_scan_d10($v, $media_by_uuid);
      $d10_media += $m;
      $d10_linked += $l;
    }
  }
  // Media reference fields on the node, its inline blocks and its paragraphs
  // (image fields, card images, background media). Every prefix is scanned:
  // stopping after the first would miss all block and paragraph media.
  foreach ([['node__', [$nid]], ['block_content__', $block_ids], ['paragraph__', $paragraph_ids]] as [$prefix, $ids]) {
    if (!$ids) {
      continue;
    }
    foreach ($ref_tables[$prefix] as $t => $col) {
      try {
        $vals = $db->query('SELECT ' . $col . ' AS v FROM {' . $t . '} WHERE entity_id IN (:i[])',
          [':i[]' => $ids])->fetchCol();
      }
      catch (\Exception $e) {
        continue;
      }
      foreach ($vals as $v) {
        if (isset($media_by_id[(int) $v])) {
          $d10_media[$media_by_id[(int) $v]] = TRUE;
        }
      }
    }
  }

  // D10 link fields: field_pub_url, field_osu_card_link and friends hold the
  // file link outside any markup.
  foreach ([['node__', [$nid]], ['block_content__', $block_ids], ['paragraph__', $paragraph_ids]] as [$prefix, $ids]) {
    if (!$ids) {
      continue;
    }
    foreach ($d10_link_tables[$prefix] as $t => $col) {
      try {
        $vals = $db->query('SELECT ' . $col . ' AS v FROM {' . $t . '} WHERE entity_id IN (:i[])',
          [':i[]' => $ids])->fetchCol();
      }
      catch (\Exception $e) {
        continue;
      }
      foreach ($vals as $v) {
        if ($name = cas_audit_file_url($v)) {
          $d10_linked[$name] = TRUE;
        }
      }
    }
  }

  // --- Compare -------------------------------------------------------------
  // Drop references whose file never existed on D7: those were broken links
  // on the old site, not something the migration lost.
  if ($d7_on_disk) {
    $d7_media = array_intersect_key($d7_media, $d7_on_disk);
    $d7_linked = array_intersect_key($d7_linked, $d7_on_disk);
  }

  $missing_media = array_keys(array_diff_key($d7_media, $d10_media));
  // A D7 link counts as satisfied if D10 either links the file or references
  // it as media: several migrations deliberately convert a D7 link field into
  // a media reference (cas_file_url_to_media does this for the enterprise
  // budget PDFs and spreadsheets), which still renders as a download link.
  // Requiring an <a> flagged 150 enterprise_budgets nodes whose files were
  // present all along. This does not mask a genuine loss like node 284766,
  // where the PDF is only ever a link target and never becomes media on the
  // node.
  $missing_links = array_keys(array_diff_key($d7_linked, $d10_linked + $d10_media));
  // A link we cannot check because the media itself is missing is reported as
  // missing media, not twice.
  $missing_links = array_values(array_diff($missing_links, $missing_media));

  if (!$missing_media && !$missing_links) {
    $stats['clean']++;
    continue;
  }
  if ($missing_media) {
    $stats['missing_media']++;
  }
  if ($missing_links) {
    $stats['missing_links']++;
  }
  $missing_by_type[$node->type] = ($missing_by_type[$node->type] ?? 0) + 1;
  if (in_array($node->type, ['story', 'page'], TRUE)) {
    $focus[$node->type]['nodes'] = ($focus[$node->type]['nodes'] ?? 0) + 1;
    $focus[$node->type]['media'] = ($focus[$node->type]['media'] ?? 0) + count($missing_media);
    $focus[$node->type]['links'] = ($focus[$node->type]['links'] ?? 0) + count($missing_links);
  }

  $rows[] = [
    $nid,
    $node->type,
    mb_substr($node->title, 0, 60),
    count($d7_media),
    count($d10_media),
    count($missing_media),
    count($missing_links),
    implode(' | ', array_slice($missing_media, 0, 5)),
    implode(' | ', array_slice($missing_links, 0, 5)),
  ];
}

usort($rows, fn($a, $b) => ($b[5] + $b[6]) <=> ($a[5] + $a[6]));

$fh = fopen($csv_path, 'w');
fputcsv($fh, ['nid', 'type', 'title', 'd7_media', 'd10_media',
  'missing_media', 'missing_links', 'missing_media_examples', 'missing_link_examples']);
foreach ($rows as $row) {
  fputcsv($fh, $row);
}
fclose($fh);

$total_missing_media = array_sum(array_column($rows, 5));
$total_missing_links = array_sum(array_column($rows, 6));

$md = [];
$md[] = '# Media fidelity audit: D7 vs D10';
$md[] = '';
$md[] = sprintf('%d nodes audited. **%d have media D7 showed and D10 does not** (%d files), '
  . 'and **%d have media that lost its link** (%d links).',
  $stats['audited'], $stats['missing_media'], $total_missing_media,
  $stats['missing_links'], $total_missing_links);
$md[] = '';
$md[] = 'Matching is by file basename, not URL: the migration renamed the site';
$md[] = 'directory and moved root-level files into year subdirectories, so comparing';
$md[] = 'paths would report thousands of false differences. A D7 media token, an';
$md[] = 'inline <img>, a file field and a 2-column background all reduce to the file';
$md[] = 'they point at, as do D10 embeds, media reference fields and Layout Builder';
$md[] = 'background settings.';
$md[] = '';
$md[] = '## Coverage';
$md[] = '';
$md[] = '| | Nodes |';
$md[] = '|---|---:|';
$md[] = sprintf('| Audited | %d |', $stats['audited']);
$md[] = sprintf('| Clean | %d |', $stats['clean']);
$md[] = sprintf('| Missing media | %d |', $stats['missing_media']);
$md[] = sprintf('| Missing links only | %d |', $stats['missing_links']);
$md[] = sprintf('| Skipped, D7 type not migrated | %d |', $stats['skipped_type']);
$md[] = sprintf('| Skipped, no D7 counterpart | %d |', $stats['no_d7']);
$md[] = '';
$md[] = 'Views embeds (viewfield, 2_column_views paragraphs) are excluded: they have';
$md[] = 'no D10 equivalent by design, so any media inside them is expected to be gone.';
$md[] = '';
$md[] = '## Stories and pages';
$md[] = '';
$md[] = 'These two types carry most of the editorial media, so they are called out';
$md[] = 'separately.';
$md[] = '';
$md[] = '| Type | Audited | With a discrepancy | Missing media | Missing links |';
$md[] = '|---|---:|---:|---:|---:|';
foreach (['story', 'page'] as $type) {
  $md[] = sprintf('| %s | %d | %d | %d | %d |', $type,
    $audited_by_type[$type] ?? 0,
    $focus[$type]['nodes'] ?? 0,
    $focus[$type]['media'] ?? 0,
    $focus[$type]['links'] ?? 0);
}
$md[] = '';
$md[] = '## Nodes with discrepancies, by content type';
$md[] = '';
$md[] = '| Type | Nodes |';
$md[] = '|---|---:|';
arsort($missing_by_type);
foreach ($missing_by_type as $type => $count) {
  $md[] = sprintf('| %s | %d |', $type, $count);
}
$md[] = '';
$md[] = '## Worst 25 nodes';
$md[] = '';
$md[] = '| nid | type | title | missing media | missing links |';
$md[] = '|---:|---|---|---:|---:|';
foreach (array_slice($rows, 0, 25) as $row) {
  $md[] = sprintf('| %d | %s | %s | %d | %d |', $row[0], $row[1], str_replace('|', '-', $row[2]), $row[5], $row[6]);
}
$md[] = '';
$md[] = sprintf('Full detail: `scripts-dev/%s`', basename($csv_path));
$md[] = '';
file_put_contents($md_path, implode("\n", $md));

printf("\naudited %d | clean %d | missing media %d nodes (%d files) | missing links %d nodes (%d links)\n",
  $stats['audited'], $stats['clean'], $stats['missing_media'], $total_missing_media,
  $stats['missing_links'], $total_missing_links);
printf("skipped: %d non-migrated type, %d no D7 counterpart\n", $stats['skipped_type'], $stats['no_d7']);
print 'report: ' . realpath($md_path) . "\n";
print 'detail: ' . realpath($csv_path) . "\n";
