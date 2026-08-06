<?php

/**
 * @file
 * Export the stage-redesigned pages from the stage_cmp database into
 * scripts-dev/stage_redesigns/redesigns.json for import_stage_redesigns.php.
 *
 * Needs the stage backup loaded as the stage_cmp database (root creds):
 *   ddev mysql -uroot -proot stage_cmp < STAGE...sql
 * Run:
 *   ddev drush --uri=agsci.oregonstate.edu scr scripts-dev/export_stage_redesigns.php
 */

use Drupal\Core\Database\Database;

const REDESIGN_NIDS = [230866, 231201, 236226];

Database::addConnectionInfo('stagecmp', 'default', [
  'database' => 'stage_cmp',
  'username' => 'root',
  'password' => 'root',
  'host' => 'db',
  'driver' => 'mysql',
]);
$st = Database::getConnection('default', 'stagecmp');

/**
 * All field-table rows for one entity, keyed by field name.
 */
$entity_fields = function (string $prefix, int $id) use ($st): array {
  $fields = [];
  foreach ($st->query("SHOW TABLES LIKE '{$prefix}\\_\\_%'")->fetchCol() as $table) {
    $field = substr($table, strlen($prefix) + 2);
    if ($field === 'layout_builder__layout') {
      continue;
    }
    try {
      $rows = $st->query("SELECT * FROM {$table} WHERE entity_id = :id ORDER BY delta", [':id' => $id])->fetchAll(\PDO::FETCH_ASSOC);
    }
    catch (\Exception $e) {
      continue;
    }
    foreach ($rows as $row) {
      $item = [];
      foreach ($row as $col => $val) {
        if (strpos($col, $field . '_') === 0) {
          $item[substr($col, strlen($field) + 1)] = $val;
        }
      }
      if ($item) {
        $fields[$field][] = $item;
      }
    }
  }
  return $fields;
};

$export = ['nodes' => [], 'blocks' => [], 'media' => [], 'files' => []];
$media_todo = [];

$collect_media_refs = function (array $fields) use (&$media_todo, $st) {
  foreach ($fields as $items) {
    foreach ($items as $item) {
      if (isset($item['target_id']) && is_numeric($item['target_id'])) {
        $mid = (int) $item['target_id'];
        $is_media = $st->query('SELECT 1 FROM {media} WHERE mid = :m', [':m' => $mid])->fetchField();
        if ($is_media) {
          $media_todo[$mid] = TRUE;
        }
      }
      // <drupal-media> embeds in rich text reference media by uuid.
      foreach ($item as $val) {
        if (is_string($val) && strpos($val, 'drupal-media') !== FALSE
          && preg_match_all('~data-entity-uuid="([0-9a-f-]{36})"~', $val, $m)) {
          foreach ($m[1] as $uuid) {
            $mid = $st->query('SELECT mid FROM {media} WHERE uuid = :u', [':u' => $uuid])->fetchField();
            if ($mid) {
              $media_todo[(int) $mid] = TRUE;
            }
          }
        }
      }
    }
  }
};

foreach (REDESIGN_NIDS as $nid) {
  $base = $st->query('SELECT n.uuid, d.type, d.title, d.status, d.created, d.changed, d.langcode, d.promote, d.sticky, d.uid FROM {node} n JOIN {node_field_data} d ON d.nid = n.nid WHERE n.nid = :n', [':n' => $nid])->fetchAssoc();
  if (!$base) {
    print "!! stage node $nid not found\n";
    continue;
  }
  $fields = $entity_fields('node', $nid);
  $collect_media_refs($fields);
  $sections = $st->query('SELECT layout_builder__layout_section FROM {node__layout_builder__layout} WHERE entity_id = :n ORDER BY delta', [':n' => $nid])->fetchCol();

  // Section-level media: bootstrap_styles background images/videos hide as
  // 'media_id' values anywhere in the section settings / component config.
  $walk_media = function ($value) use (&$walk_media, &$media_todo) {
    if (!is_array($value)) {
      return;
    }
    foreach ($value as $key => $item) {
      if ($key === 'media_id' && is_scalar($item) && is_numeric($item) && (int) $item > 0) {
        $media_todo[(int) $item] = TRUE;
      }
      else {
        $walk_media($item);
      }
    }
  };
  foreach ($sections as $s) {
    $section = unserialize($s, ['allowed_classes' => [\Drupal\layout_builder\Section::class, \Drupal\layout_builder\SectionComponent::class]]);
    if ($section instanceof \Drupal\layout_builder\Section) {
      $walk_media($section->toArray());
    }
  }
  $alias = $st->query('SELECT alias FROM {path_alias} WHERE path = :p AND status = 1 ORDER BY id DESC', [':p' => "/node/$nid"])->fetchField();

  // Inline blocks referenced by the sections, by revision id.
  $revids = [];
  foreach ($sections as $s) {
    if (preg_match_all('~"block_revision_id";(?:s:\d+:"(\d+)"|i:(\d+))~', $s, $m, PREG_SET_ORDER)) {
      foreach ($m as $hit) {
        $revids[] = (int) ($hit[1] !== '' ? $hit[1] : $hit[2]);
      }
    }
  }
  foreach (array_unique($revids) as $rev) {
    $brow = $st->query('SELECT r.id, b.uuid, b.type, d.info, d.reusable FROM {block_content_revision} r JOIN {block_content} b ON b.id = r.id JOIN {block_content_field_data} d ON d.id = r.id WHERE r.revision_id = :r', [':r' => $rev])->fetchAssoc();
    if (!$brow) {
      print "!! node $nid: block revision $rev unresolved\n";
      continue;
    }
    $bfields = $entity_fields('block_content', (int) $brow['id']);
    $collect_media_refs($bfields);
    $export['blocks'][$rev] = [
      'uuid' => $brow['uuid'],
      'type' => $brow['type'],
      'info' => $brow['info'],
      'fields' => $bfields,
    ];
  }

  $export['nodes'][] = [
    'stage_nid' => $nid,
    'uuid' => $base['uuid'],
    'type' => $base['type'],
    'title' => $base['title'],
    'status' => (int) $base['status'],
    'created' => (int) $base['created'],
    'changed' => (int) $base['changed'],
    'langcode' => $base['langcode'],
    'promote' => (int) $base['promote'],
    'sticky' => (int) $base['sticky'],
    'alias' => $alias ? '/' . ltrim($alias, '/') : NULL,
    'fields' => $fields,
    'sections_b64' => array_map('base64_encode', $sections),
  ];
  print "exported node $nid '{$base['title']}' (" . count($sections) . " sections)\n";
}

// Media + their files.
foreach (array_keys($media_todo) as $mid) {
  $mrow = $st->query('SELECT m.uuid, d.bundle, d.name FROM {media} m JOIN {media_field_data} d ON d.mid = m.mid WHERE m.mid = :m', [':m' => $mid])->fetchAssoc();
  $mfields = $entity_fields('media', $mid);
  // File targets inside media source fields.
  foreach ($mfields as $items) {
    foreach ($items as $item) {
      if (isset($item['target_id']) && is_numeric($item['target_id'])) {
        $frow = $st->query('SELECT uuid, uri, filename, filemime FROM {file_managed} WHERE fid = :f', [':f' => $item['target_id']])->fetchAssoc();
        if ($frow) {
          $export['files'][(int) $item['target_id']] = $frow;
        }
      }
    }
  }
  $export['media'][$mid] = ['uuid' => $mrow['uuid'], 'bundle' => $mrow['bundle'], 'name' => $mrow['name'], 'fields' => $mfields];
}

printf("blocks: %d, media: %d, files: %d\n", count($export['blocks']), count($export['media']), count($export['files']));
$dir = '/var/www/html/scripts-dev/stage_redesigns';
if (!is_dir($dir)) {
  mkdir($dir, 0775, TRUE);
}
file_put_contents($dir . '/redesigns.json', json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
print "written to scripts-dev/stage_redesigns/redesigns.json\n";
