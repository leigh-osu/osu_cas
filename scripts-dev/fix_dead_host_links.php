<?php

/**
 * Rewrite content links that still point at retired hostnames.
 *
 * Three former department hostnames no longer negotiate at the Acquia edge
 * (401 on every path), so every link to them is publicly broken:
 *
 *   fw.oregonstate.edu                   -> fwcs.oregonstate.edu
 *   centerforsmallfarms.oregonstate.edu  -> crafs.oregonstate.edu
 *   fwl.oregonstate.edu                  -> (pre-Drupal static site; one page
 *                                           maps to a profile, the rest are gone)
 *
 * A link is rewritten only when the replacement URL actually serves (HTTP
 * 200 after redirects, checked live and cached per URL). Anything else is
 * left untouched and written to scripts-dev/dead_host_links_unresolved.csv
 * for editors: old static .htm pages, /content/ aliases, user-account and
 * edit URLs, profiles of people no longer on the site, two missing PDFs.
 *
 * Covers every text/link column on node, block_content (Layout Builder
 * inline blocks), paragraph, group, menu_link_content and redirect entities
 * — the 2026-09-02 small-farms rewrite only handled bodies and menu links,
 * which left card links, menu-bar items, profile links, publication URLs and
 * accordion bodies behind. Saves are in place (no new revisions: LB pins
 * block revision ids) with syncing on. Idempotent.
 *
 *   drush scr scripts-dev/fix_dead_host_links.php              (dry run)
 *   drush scr scripts-dev/fix_dead_host_links.php -- --apply
 *
 * On prod, scripts-dev is not deployed: scp the file to the home directory
 * and run it from there.
 */

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\SynchronizableInterface;

$apply = in_array('--apply', $extra ?? [], TRUE);
$mode = $apply ? 'APPLY' : 'DRY RUN';
print "== $mode ==\n";

$db = \Drupal::database();
$http = \Drupal::httpClient();

// Dead host => successor host. NULL means no host-level successor: only the
// explicit URL map below can resolve it.
$hosts = [
  'fw.oregonstate.edu' => 'fwcs.oregonstate.edu',
  'centerforsmallfarms.oregonstate.edu' => 'crafs.oregonstate.edu',
  'fwl.oregonstate.edu' => NULL,
];
// Exact URL (scheme-less, host + path) => replacement.
$url_map = [
  'fwl.oregonstate.edu/About%20Us/personnel/faculty/sidlauskas.html' => 'https://fwcs.oregonstate.edu/people/brian-sidlauskas',
];

// Columns to sweep: every text/link column on the content entity tables,
// minus ids/metadata and the redirect *source* path (that is a path on this
// site, not a link out).
$schema = $db->getConnectionOptions()['database'];
$columns = $db->query("SELECT table_name t, column_name c FROM information_schema.columns
  WHERE table_schema = :s AND data_type IN ('varchar','text','mediumtext','longtext')
  AND (table_name LIKE 'node\\_\\_%' OR table_name LIKE 'block\\_content\\_\\_%' OR table_name LIKE 'paragraph\\_\\_%'
       OR table_name LIKE 'group\\_\\_%' OR table_name = 'menu_link_content_data' OR table_name = 'redirect')
  AND column_name NOT IN ('bundle','langcode','entity_id','revision_id','delta','uuid','id','title','redirect_source__path')
  ORDER BY table_name, column_name", [':s' => $schema])->fetchAll();

// --- URL resolution --------------------------------------------------------
$status_cache = [];
$serves = function (string $url) use ($http, &$status_cache): bool {
  $key = html_entity_decode($url);
  if (!array_key_exists($key, $status_cache)) {
    try {
      $r = $http->request('GET', $key, ['allow_redirects' => ['max' => 5], 'http_errors' => FALSE, 'timeout' => 20, 'headers' => ['User-Agent' => 'osu-cas link repair']]);
      $status_cache[$key] = $r->getStatusCode();
    }
    catch (\Throwable $e) {
      $status_cache[$key] = 0;
    }
  }
  return $status_cache[$key] === 200;
};

$unresolved = [];   // url => list of "entity:id field"
$places = [];       // url => list of "entity:id field" (every URL, resolved or not)
$attempts = [];     // url => "candidate (status)" of the last candidate tried
$resolutions = [];  // old url => new url (for the summary)
$resolve = function (string $match, string $host, string $where) use ($hosts, $url_map, $serves, &$status_cache, &$unresolved, &$places, &$attempts, &$resolutions): ?string {
  $places[$match][] = $where;
  if (isset($resolutions[$match])) {
    return $resolutions[$match];
  }
  // Split scheme / host / rest.
  if (!preg_match('~^(https?://)?(?:www\.)?' . preg_quote($host, '~') . '(.*)$~i', $match, $m)) {
    return NULL;
  }
  $scheme = $m[1] ?: '';
  $rest = $m[2];
  // Drop Google Analytics linker junk (query may be HTML-escaped in bodies).
  $rest = preg_replace('~(?:\?|&amp;|&)(?:_ga|_gl)=[^&#]*~', '', $rest);
  $rest = preg_replace('~^([^?#]*)(?:&amp;|&)~', '$1?', $rest);
  $rest = preg_replace('~\?(?=#|$)~', '', $rest);
  $path = preg_replace('~[?#].*$~', '', $rest);
  $key = $host . $path;
  // Candidates in order of preference.
  $candidates = [];
  if (isset($url_map[$key])) {
    $candidates[] = $url_map[$key];
  }
  elseif ($hosts[$host]) {
    $candidates[] = ($scheme ? 'https://' : '') . $hosts[$host] . $rest;
    // D7 account URLs (/users/<name>) are profile nodes at /people/<name>
    // on the D10 sites; the account path itself is 403 to the public.
    if (preg_match('~^/users/([^/]+)/?$~', $path, $u)) {
      $candidates[] = ($scheme ? 'https://' : '') . $hosts[$host] . '/people/' . $u[1] . preg_replace('~^[^?#]*~', '', $rest);
    }
  }
  foreach ($candidates as $new) {
    $check = str_starts_with($new, 'http') ? $new : 'https://' . $new;
    $ok = $serves($check);
    $attempts[$match] = $check . ' (' . $status_cache[html_entity_decode($check)] . ')';
    if ($ok) {
      $resolutions[$match] = $new;
      return $new;
    }
  }
  if (!$candidates) {
    $attempts[$match] = 'no successor';
  }
  $unresolved[$match][] = $where;
  return NULL;
};

$pattern = '~(?:https?://)?(?:www\.)?(' . implode('|', array_map(fn($h) => preg_quote($h, '~'), array_keys($hosts))) . ')(?:/[^\s"\'<>)\]]*)?~i';
$rewrite = function (string $text, string $where) use ($pattern, $resolve): string {
  return preg_replace_callback($pattern, function ($m) use ($resolve, $where) {
    $url = $m[0];
    // Stop at an HTML entity other than &amp; (e.g. a trailing &nbsp;) and
    // at sentence punctuation.
    $url = preg_replace('~&(?!amp;)[a-zA-Z]+;.*$~s', '', $url);
    $url = rtrim($url, '.,;');
    $tail = substr($m[0], strlen($url));
    $new = $resolve($url, strtolower($m[1]), $where);
    return ($new ?? $url) . $tail;
  }, $text);
};

// --- find affected entities -----------------------------------------------
// table => [entity_type, field or NULL for base-table columns]
$targets = [];  // "type:id" => [type, id, [field names]]
$like = array_map(fn($h) => "%$h%", array_keys($hosts));
foreach ($columns as $col) {
  $t = $col->t;
  if ($t === 'menu_link_content_data') {
    [$type, $field, $id_col] = ['menu_link_content', 'link', 'id'];
  }
  elseif ($t === 'redirect') {
    [$type, $field, $id_col] = ['redirect', 'redirect_redirect', 'rid'];
    if ($col->c !== 'redirect_redirect__uri') {
      continue;
    }
  }
  elseif (preg_match('~^(node|block_content|paragraph|group)__(.+)$~', $t, $m)) {
    [$type, $field, $id_col] = [$m[1], $m[2], 'entity_id'];
  }
  else {
    continue;
  }
  $where = implode(' OR ', array_fill(0, count($like), "`{$col->c}` LIKE ?"));
  $ids = $db->query("SELECT DISTINCT `$id_col` FROM {" . $t . "} WHERE $where", $like)->fetchCol();
  foreach ($ids as $id) {
    $targets["$type:$id"]['type'] = $type;
    $targets["$type:$id"]['id'] = $id;
    $targets["$type:$id"]['fields'][$field] = TRUE;
  }
}
printf("%d entities reference a dead host\n\n", count($targets));

// --- rewrite ----------------------------------------------------------------
$etm = \Drupal::entityTypeManager();
$changed_entities = 0;
$changed_values = 0;
foreach ($targets as $key => $t) {
  $entity = $etm->getStorage($t['type'])->load($t['id']);
  if (!$entity instanceof ContentEntityInterface) {
    print "$key: could not load\n";
    continue;
  }
  $entity_changed = FALSE;
  foreach (array_keys($t['fields']) as $field) {
    if (!$entity->hasField($field)) {
      continue;
    }
    $items = $entity->get($field);
    foreach ($items as $delta => $item) {
      $values = $item->getValue();
      $new_values = $values;
      foreach ($values as $prop => $v) {
        if (!is_string($v) || !preg_match($pattern, $v)) {
          continue;
        }
        $new = $rewrite($v, "$key $field[$delta].$prop");
        if ($new !== $v) {
          $new_values[$prop] = $new;
          $changed_values++;
          printf("%s %s[%d].%s\n    %s\n -> %s\n", $key, $field, $delta, $prop,
            mb_strimwidth(preg_replace('~\s+~', ' ', $v), 0, 160, '…'),
            mb_strimwidth(preg_replace('~\s+~', ' ', $new), 0, 160, '…'));
        }
      }
      if ($new_values !== $values) {
        $item->setValue($new_values);
        $entity_changed = TRUE;
      }
    }
  }
  if ($entity_changed) {
    $changed_entities++;
    if ($apply) {
      if ($entity instanceof RevisionableInterface) {
        $entity->setNewRevision(FALSE);
      }
      if ($entity instanceof SynchronizableInterface) {
        $entity->setSyncing(TRUE);
      }
      $entity->save();
    }
  }
}

// --- report -----------------------------------------------------------------
print "\n== resolved URLs (" . count($resolutions) . ")\n";
ksort($resolutions);
foreach ($resolutions as $old => $new) {
  print "  $old\n    -> $new\n";
}
print "\n== unresolved URLs (" . count($unresolved) . ") — left in place, see CSV\n";
ksort($unresolved);
foreach ($unresolved as $url => $where_list) {
  printf("  %s  [%s]  %s\n", $url, $attempts[$url] ?? 'no successor', implode('; ', array_unique($where_list)));
}

// --- node ids behind each place ---------------------------------------------
// node:N is itself; paragraphs walk up to their host node; inline blocks are
// found through the Layout Builder sections that pin one of their revisions
// (plus inline_block_usage for post-migration blocks). Menu links and
// redirects have no node.
$node_cache = [];
$nodes_for = function (string $place) use ($db, &$node_cache, &$nodes_for): array {
  [$ref] = explode(' ', $place, 2);
  if (isset($node_cache[$ref])) {
    return $node_cache[$ref];
  }
  [$type, $id] = explode(':', $ref);
  $nids = [];
  if ($type === 'node') {
    $nids = [(int) $id];
  }
  elseif ($type === 'paragraph') {
    $ptype = 'paragraph';
    $pid = $id;
    for ($depth = 0; $depth < 10 && $ptype === 'paragraph'; $depth++) {
      $row = $db->query('SELECT parent_type, parent_id FROM {paragraphs_item_field_data} WHERE id = :id', [':id' => $pid])->fetchAssoc();
      if (!$row) {
        break;
      }
      [$ptype, $pid] = [$row['parent_type'], $row['parent_id']];
    }
    if ($ptype === 'node') {
      $nids = [(int) $pid];
    }
    elseif ($ptype === 'block_content') {
      // Accordion items live in paragraphs inside an inline block.
      $nids = $nodes_for("block_content:$pid x");
    }
  }
  elseif ($type === 'block_content') {
    $rids = $db->query('SELECT revision_id FROM {block_content_revision} WHERE id = :id', [':id' => $id])->fetchCol();
    foreach ($rids as $rid) {
      $found = $db->query('SELECT entity_id FROM {node__layout_builder__layout} WHERE layout_builder__layout_section LIKE :p',
        [':p' => '%"block_revision_id";i:' . (int) $rid . ';%'])->fetchCol();
      $nids = array_merge($nids, array_map('intval', $found));
    }
    $usage = $db->query("SELECT layout_entity_id FROM {inline_block_usage} WHERE block_content_id = :id AND layout_entity_type = 'node'", [':id' => $id])->fetchCol();
    $nids = array_merge($nids, array_map('intval', $usage));
  }
  $nids = array_values(array_unique($nids));
  sort($nids);
  return $node_cache[$ref] = $nids;
};
$node_ids_for = function (array $where_list) use ($nodes_for): string {
  $nids = [];
  foreach (array_unique($where_list) as $place) {
    $nids = array_merge($nids, $nodes_for($place));
  }
  $nids = array_unique($nids);
  sort($nids);
  return implode(' ', $nids);
};

// --- CSVs -------------------------------------------------------------------
$dir = '/tmp';
$repo_dir = dirname(__DIR__) . '/scripts-dev';
if (is_dir($repo_dir) && is_writable($repo_dir)) {
  $dir = $repo_dir;
}
$fh = fopen("$dir/dead_host_links_replacements.csv", 'w');
fputcsv($fh, ['old_url', 'new_url', 'occurrences', 'node_ids', 'used_in']);
foreach ($resolutions as $old => $new) {
  $where_list = $places[$old] ?? [];
  fputcsv($fh, [$old, $new, count($where_list), $node_ids_for($where_list), implode('; ', array_unique($where_list))]);
}
fclose($fh);
$fh = fopen("$dir/dead_host_links_unresolved.csv", 'w');
fputcsv($fh, ['url', 'checked_replacement', 'status', 'occurrences', 'node_ids', 'used_in']);
foreach ($unresolved as $url => $where_list) {
  $tried = $attempts[$url] ?? 'no successor';
  $status = preg_match('~\((\d+)\)$~', $tried, $m) ? $m[1] : 'no successor';
  fputcsv($fh, [$url, $status === 'no successor' ? '' : preg_replace('~ \(\d+\)$~', '', $tried), $status, count($where_list), $node_ids_for($where_list), implode('; ', array_unique($where_list))]);
}
fclose($fh);
printf("\nCSVs: %s/dead_host_links_replacements.csv, %s/dead_host_links_unresolved.csv\n", $dir, $dir);
printf("== done (%s): %d value(s) on %d entit%s %s; %d URL(s) unresolved\n", $mode, $changed_values, $changed_entities,
  $changed_entities === 1 ? 'y' : 'ies', $apply ? 'saved' : 'would change', count($unresolved));
