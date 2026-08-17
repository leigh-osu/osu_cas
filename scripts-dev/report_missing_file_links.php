<?php

/**
 * @file
 * Report every content link to a file that does not exist, with the page it
 * sits on.
 *
 * Answers "what is broken and where do I go to fix it": for each missing file
 * the CSV lists the referring entity, its title, its URL, the field the link
 * lives in, and whether the file still exists in the D7 source tree (i.e.
 * whether it is recoverable by copying, or gone for good).
 *
 * Writes scripts-dev/missing_file_links.csv.
 *
 * Optional: set D7_FILES to the D7 public files directory to get the
 * "in_d7" column filled in (default ~/Sites/osu/agscid7/docroot/sites/default/files).
 *
 * Usage: drush scr scripts-dev/report_missing_file_links.php
 */

$db = \Drupal::database();
$schema = $db->schema();
$fs = \Drupal::service('file_system');
$public = rtrim($fs->realpath('public://'), '/') . '/';
$private_real = $fs->realpath('private://');
$private = $private_real ? rtrim($private_real, '/') . '/' : NULL;
$d7 = getenv('D7_FILES') ?: (getenv('HOME') . '/Sites/osu/agscid7/docroot/sites/default/files');
$d7 = rtrim($d7, '/') . '/';

/**
 * Pull the file-relative path out of a link, or NULL if it is not a file link.
 */
$to_rel = function (string $raw): ?array {
  $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
  $raw = strtok($raw, '#');
  $raw = strtok($raw, '?');
  if (!$raw) {
    return NULL;
  }
  $host = NULL;
  if (preg_match('~^(https?:)?//([^/]+)(/.*)?$~i', $raw, $m)) {
    $host = strtolower($m[2]);
    if (!preg_match('~(oregonstate\.edu|spottedwing\.org|infews\.org|open-sensing\.org|ddev\.site)$~', $host)) {
      return NULL;
    }
    $raw = $m[3] ?? '/';
  }
  $raw = rawurldecode($raw);
  if (preg_match('~^/?system/files/(.+)$~', $raw, $m)) {
    return ['private', preg_replace('~/+~', '/', $m[1]), $host];
  }
  foreach (['~^/?sites/([^/]+)/files/~', '~^/?files/~'] as $re) {
    if (preg_match($re, $raw)) {
      $rel = ltrim(preg_replace($re, '', $raw), '/');
      $rel = preg_replace('~/+~', '/', $rel);
      return $rel === '' ? NULL : ['public', $rel, $host];
    }
  }
  return NULL;
};

// ---------------------------------------------------------------- scan
$columns = $db->query("
  SELECT TABLE_NAME AS t, COLUMN_NAME AS c
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND DATA_TYPE IN ('longtext', 'text', 'mediumtext', 'varchar')
    AND (COLUMN_NAME LIKE '%_value' OR COLUMN_NAME LIKE '%_uri' OR COLUMN_NAME = 'link__uri'
         OR COLUMN_NAME = 'layout_builder__layout_section')
    AND TABLE_NAME NOT LIKE 'migrate_%'
    AND TABLE_NAME NOT LIKE 'cache%'
    AND TABLE_NAME NOT LIKE 'search_%'
    AND TABLE_NAME NOT LIKE '%_revision%'
")->fetchAll();

$findings = [];
foreach ($columns as $col) {
  if (!$schema->tableExists($col->t)) {
    continue;
  }
  $has_entity = $schema->fieldExists($col->t, 'entity_id');
  $key = $has_entity ? 'entity_id' : ($schema->fieldExists($col->t, 'id') ? 'id' : ($schema->fieldExists($col->t, 'rid') ? 'rid' : NULL));
  $bundle_col = $schema->fieldExists($col->t, 'bundle') ? 'bundle' : NULL;
  $select = 'SELECT ' . ($key ? "`$key` AS k, " : "NULL AS k, ") . ($bundle_col ? "`$bundle_col` AS b, " : "NULL AS b, ") . '`' . $col->c . '` AS v FROM {' . $col->t . '}';
  foreach ($db->query($select) as $row) {
    if (!$row->v) {
      continue;
    }
    $urls = [];
    if (preg_match_all('~(?:href|src)\s*=\s*["\']([^"\']+)["\']~i', $row->v, $m)) {
      $urls = $m[1];
    }
    $t = trim($row->v);
    if (preg_match('~^(internal:|base:|/|https?://|//)~i', $t)) {
      $urls[] = preg_replace('~^(internal:|base:)~', '', $t);
    }
    foreach ($urls as $u) {
      $parsed = $to_rel($u);
      if (!$parsed) {
        continue;
      }
      [$scheme, $rel, $host] = $parsed;
      $base = $scheme === 'private' ? $private : $public;
      if ($base && file_exists($base . $rel)) {
        continue;
      }
      $findings[] = [
        'scheme' => $scheme,
        'rel' => $rel,
        'table' => $col->t,
        'column' => $col->c,
        'key' => $row->k,
        'bundle' => $row->b,
        'host' => $host,
        'link' => $u,
      ];
    }
  }
}

// ---------------------------------------------------------------- resolve referrers
$entity_type = function (string $table): ?string {
  if ($table === 'menu_link_content_data') {
    return 'menu_link_content';
  }
  if ($table === 'redirect') {
    return 'redirect';
  }
  if (preg_match('~^([a-z_]+?)__~', $table, $m)) {
    return match ($m[1]) {
      'node' => 'node',
      'block_content' => 'block_content',
      'paragraph' => 'paragraph',
      'media' => 'media',
      'group' => 'group',
      'taxonomy_term' => 'taxonomy_term',
      'user' => 'user',
      default => $m[1],
    };
  }
  return NULL;
};

$etm = \Drupal::entityTypeManager();
$alias_manager = \Drupal::service('path_alias.manager');
$label_cache = [];
$describe = function (?string $type, $id) use ($etm, $alias_manager, &$label_cache, $db) {
  if (!$type || !$id) {
    return ['', '', ''];
  }
  $ck = "$type:$id";
  if (isset($label_cache[$ck])) {
    return $label_cache[$ck];
  }
  $label = $url = $extra = '';
  try {
    if ($type === 'redirect') {
      $row = $db->query('SELECT redirect_source__path FROM {redirect} WHERE rid = :r', [':r' => $id])->fetchField();
      $label = 'redirect from /' . $row;
      $url = '/' . $row;
    }
    elseif ($etm->hasDefinition($type)) {
      $entity = $etm->getStorage($type)->load($id);
      if ($entity) {
        $label = (string) $entity->label();
        if ($type === 'node') {
          $url = $alias_manager->getAliasByPath('/node/' . $id);
        }
        elseif ($type === 'paragraph') {
          $host = $entity->getParentEntity();
          if ($host) {
            $extra = $host->getEntityTypeId() . ':' . $host->id() . ' ' . $host->label();
            if ($host->getEntityTypeId() === 'node') {
              $url = $alias_manager->getAliasByPath('/node/' . $host->id());
            }
          }
        }
        elseif ($type === 'block_content') {
          // Inline blocks are pinned into a node layout by revision id, which
          // serializes as an integer: s:17:"block_revision_id";i:18010;
          $rev = $entity->getRevisionId();
          $nid = $db->query('SELECT entity_id FROM {node__layout_builder__layout} WHERE layout_builder__layout_section LIKE :r LIMIT 1', [':r' => '%"block_revision_id";i:' . $rev . ';%'])->fetchField();
          if (!$nid) {
            // Reusable blocks are referenced by uuid instead.
            $nid = $db->query('SELECT entity_id FROM {node__layout_builder__layout} WHERE layout_builder__layout_section LIKE :r LIMIT 1', [':r' => '%' . $entity->uuid() . '%'])->fetchField();
          }
          if ($nid) {
            $extra = 'node:' . $nid;
            $url = $alias_manager->getAliasByPath('/node/' . $nid);
          }
        }
        elseif ($type === 'menu_link_content') {
          $extra = $entity->getMenuName();
        }
      }
    }
  }
  catch (\Throwable $e) {
    $label = '(' . $e->getMessage() . ')';
  }
  return $label_cache[$ck] = [$label, $url, $extra];
};

// ---------------------------------------------------------------- write
$out = DRUPAL_ROOT . '/../scripts-dev/missing_file_links.csv';
$fh = fopen($out, 'w');
fputcsv($fh, [
  'file_path', 'scheme', 'in_d7_source', 'referring_entity', 'entity_id',
  'bundle', 'title', 'page_url', 'host_or_parent', 'field', 'raw_link',
]);
usort($findings, fn($a, $b) => [$a['rel'], $a['table']] <=> [$b['rel'], $b['table']]);
$paths = [];
foreach ($findings as $f) {
  $type = $entity_type($f['table']);
  [$label, $url, $extra] = $describe($type, $f['key']);
  $in_d7 = file_exists($d7 . $f['rel']) ? 'yes' : 'no';
  $paths[$f['rel']] = $in_d7;
  fputcsv($fh, [
    ($f['scheme'] === 'private' ? 'private://' : '') . $f['rel'],
    $f['scheme'],
    $in_d7,
    $type ?? $f['table'],
    $f['key'],
    $f['bundle'],
    $label,
    $url,
    $extra ?: ($f['host'] ?? ''),
    $f['column'],
    $f['link'],
  ]);
}
fclose($fh);

$recoverable = count(array_filter($paths, fn($v) => $v === 'yes'));
printf("missing-file references: %d\n", count($findings));
printf("distinct missing files:  %d (%d recoverable from the D7 tree, %d gone)\n",
  count($paths), $recoverable, count($paths) - $recoverable);
printf("wrote %s\n", $out);
