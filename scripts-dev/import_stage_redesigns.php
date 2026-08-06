<?php

/**
 * @file
 * Recreate the stage-redesigned Home / Education / About pages as NEW
 * nodes from scripts-dev/stage_redesigns/redesigns.json (exported from
 * the stage backup by export_stage_redesigns.php).
 *
 * The D7-migrated originals are kept untouched except their titles gain
 * a "D7 version: " prefix, and the public aliases (/home/home,
 * /education, /home/about) are repointed at the new nodes. Idempotent:
 * everything is keyed by the stage UUIDs.
 *
 * Physical files missing locally are copied in from
 * scripts-dev/"D10 assets for Roger" (space/underscore name variants).
 *
 * Run late in the rebuild, after all migrations:
 *   ddev drush --uri=agsci.oregonstate.edu scr scripts-dev/import_stage_redesigns.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\file\Entity\File;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;

$data = json_decode(file_get_contents('/var/www/html/scripts-dev/stage_redesigns/redesigns.json'), TRUE);
$etm = \Drupal::entityTypeManager();
$fs = \Drupal::service('file_system');
$assets = '/var/www/html/scripts-dev/D10 assets for Roger';

$by_uuid = function (string $type, string $uuid) use ($etm) {
  $found = $etm->getStorage($type)->loadByProperties(['uuid' => $uuid]);
  return $found ? reset($found) : NULL;
};

// Field names (node + block_content) whose entity_reference target is media
// — only those get stage-mid -> local-mid remapping.
$media_fields = [];
foreach (['node', 'block_content'] as $et) {
  foreach ($etm->getStorage('field_storage_config')->loadByProperties(['entity_type' => $et]) as $storage) {
    if ($storage->getType() === 'entity_reference' && $storage->getSetting('target_type') === 'media') {
      $media_fields[$storage->getName()] = TRUE;
    }
  }
}

// --- 1. Files ---------------------------------------------------------
$fid_map = [];
foreach ($data['files'] as $stage_fid => $f) {
  if ($existing = $by_uuid('file', $f['uuid'])) {
    $fid_map[$stage_fid] = (int) $existing->id();
    continue;
  }
  // Same URI already registered under a different uuid? Reuse it.
  $same_uri = $etm->getStorage('file')->loadByProperties(['uri' => $f['uri']]);
  if ($same_uri) {
    $fid_map[$stage_fid] = (int) reset($same_uri)->id();
    continue;
  }
  $real = $fs->realpath($f['uri']);
  if (!$real || !file_exists($real)) {
    // Pull from the assets folder; stage names use underscores where the
    // asset files may use spaces (and vice versa), and some assets have
    // the same words in a different order ("hero strand dark" vs
    // "strand hero dark") — match on the sorted word set + extension.
    $base = basename($f['uri']);
    $candidates = [$base, str_replace('_', ' ', $base), str_replace(' ', '_', $base)];
    $found = NULL;
    foreach ($candidates as $c) {
      if (file_exists("$assets/$c")) {
        $found = "$assets/$c";
        break;
      }
    }
    if (!$found) {
      $tokens = function (string $name): array {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $words = preg_split('~[\s_\-]+~', strtolower(pathinfo($name, PATHINFO_FILENAME)));
        sort($words);
        return [$ext, $words];
      };
      [$want_ext, $want_words] = $tokens($base);
      foreach (scandir($assets) as $candidate) {
        if ($candidate[0] === '.') {
          continue;
        }
        [$ext, $words] = $tokens($candidate);
        if ($ext === $want_ext && $words === $want_words) {
          $found = "$assets/$candidate";
          break;
        }
      }
    }
    if (!$found) {
      print "  !! missing physical file for {$f['uri']} — not in assets dir\n";
      continue;
    }
    $dir = dirname($f['uri']);
    $fs->prepareDirectory($dir, 1 | 2);
    $fs->copy($found, $f['uri'], 1);
    print "  + copied asset $base -> {$f['uri']}\n";
  }
  $file = File::create([
    'uuid' => $f['uuid'],
    'uri' => $f['uri'],
    'filename' => $f['filename'],
    'filemime' => $f['filemime'],
    'status' => 1,
    'uid' => 1,
  ]);
  $file->save();
  $fid_map[$stage_fid] = (int) $file->id();
  print "  + created file {$file->id()} ({$f['filename']})\n";
}

// --- 2. Media ---------------------------------------------------------
$mid_map = [];
foreach ($data['media'] as $stage_mid => $m) {
  if ($existing = $by_uuid('media', $m['uuid'])) {
    $mid_map[$stage_mid] = (int) $existing->id();
    continue;
  }
  $values = ['uuid' => $m['uuid'], 'bundle' => $m['bundle'], 'name' => $m['name'], 'status' => 1, 'uid' => 1];
  foreach ($m['fields'] as $field => $items) {
    foreach ($items as $item) {
      if (isset($item['target_id']) && isset($fid_map[$item['target_id']])) {
        $item['target_id'] = $fid_map[$item['target_id']];
      }
      $values[$field][] = $item;
    }
  }
  $media = Media::create($values);
  $media->save();
  $mid_map[$stage_mid] = (int) $media->id();
  print "  + created media {$media->id()} ({$m['name']})\n";
}

$remap_fields = function (array $fields) use ($media_fields, $mid_map): array {
  foreach ($fields as $field => &$items) {
    foreach ($items as &$item) {
      if (isset($media_fields[$field]) && isset($item['target_id']) && isset($mid_map[$item['target_id']])) {
        $item['target_id'] = $mid_map[$item['target_id']];
      }
      // Raw DB columns come through as-is; serialized ones (link options,
      // metatag values, ...) must be arrays again or formatters fatal.
      foreach ($item as &$col) {
        if (is_string($col) && preg_match('~^a:\d+:\{~', $col)) {
          $decoded = @unserialize($col, ['allowed_classes' => FALSE]);
          if (is_array($decoded)) {
            $col = $decoded;
          }
        }
      }
      unset($col);
    }
    unset($item);
  }
  return $fields;
};

// --- 3. Inline blocks -------------------------------------------------
$rev_map = [];
foreach ($data['blocks'] as $stage_rev => $b) {
  if ($existing = $by_uuid('block_content', $b['uuid'])) {
    $rev_map[$stage_rev] = (int) $existing->getRevisionId();
    continue;
  }
  $values = [
    'uuid' => $b['uuid'],
    'type' => $b['type'],
    'info' => $b['info'] ?: 'Stage redesign block',
    'reusable' => 0,
  ];
  foreach ($remap_fields($b['fields']) as $field => $items) {
    $values[$field] = $items;
  }
  $block = BlockContent::create($values);
  $block->save();
  $rev_map[$stage_rev] = (int) $block->getRevisionId();
}
print "  blocks in place: " . count($rev_map) . "\n";

// --- 4. Nodes ---------------------------------------------------------
foreach ($data['nodes'] as $n) {
  // Retitle the migrated original and free its alias regardless (idempotent).
  $old = Node::load($n['stage_nid']);
  if ($old && strpos($old->label(), 'D7 version: ') !== 0) {
    $old->setTitle('D7 version: ' . $old->label());
    $old->save();
    print "  ~ retitled node {$old->id()} -> '{$old->label()}'\n";
  }

  $node = $by_uuid('node', $n['uuid']);
  if ($node) {
    print "  = node '{$n['title']}' already imported\n";
  }
  else {

  $values = [
    'uuid' => $n['uuid'],
    'type' => $n['type'],
    'title' => $n['title'],
    'status' => $n['status'],
    'created' => $n['created'],
    'changed' => $n['changed'],
    'langcode' => $n['langcode'],
    'promote' => $n['promote'],
    'sticky' => $n['sticky'],
    'uid' => 1,
    'path' => ['pathauto' => 0],
  ];
  foreach ($remap_fields($n['fields']) as $field => $items) {
    $values[$field] = $items;
  }

  $node = Node::create($values);
  // Rebuild the Layout Builder sections: remap inline-block revisions AND
  // every bootstrap_styles background 'media_id' (section settings and
  // component config alike) from stage ids to local ones.
  $remap_section = function ($value) use (&$remap_section, $rev_map, $mid_map, $n) {
    if (!is_array($value)) {
      return $value;
    }
    foreach ($value as $key => $item) {
      if ($key === 'block_revision_id' && is_scalar($item) && isset($rev_map[(int) $item])) {
        $value[$key] = $rev_map[(int) $item];
      }
      elseif ($key === 'media_id' && is_scalar($item) && is_numeric($item) && (int) $item > 0) {
        if (isset($mid_map[(int) $item])) {
          $value[$key] = $mid_map[(int) $item];
        }
        else {
          print "  !! '{$n['title']}': no local media for stage mid $item\n";
        }
      }
      else {
        $value[$key] = $remap_section($item);
      }
    }
    return $value;
  };
  $sections = [];
  foreach ($n['sections_b64'] as $blob) {
    $section = unserialize(base64_decode($blob), ['allowed_classes' => [Section::class, SectionComponent::class]]);
    if (!$section instanceof Section) {
      print "  !! unserializable section on '{$n['title']}'\n";
      continue;
    }
    $section = Section::fromArray($remap_section($section->toArray()));
    $sections[] = ['section' => $section];
  }
  $node->set('layout_builder__layout', $sections);
  $node->save();
  print "  + created node {$node->id()} '{$n['title']}' (" . count($sections) . " sections)\n";

  // Point the public alias at the new node (repoint if it exists).
  if ($n['alias']) {
    $repointed = FALSE;
    foreach ($etm->getStorage('path_alias')->loadByProperties(['alias' => $n['alias']]) as $alias) {
      $alias->setPath('/node/' . $node->id());
      $alias->save();
      $repointed = TRUE;
    }
    if (!$repointed) {
      PathAlias::create(['path' => '/node/' . $node->id(), 'alias' => $n['alias'], 'langcode' => $n['langcode']])->save();
    }
    print "    alias {$n['alias']} -> node {$node->id()}\n";
  }
  }

  // --- Parity with the migrated original: group placement, menu links ---
  if ($old && $node) {
    // Same group placements as the old node.
    $rels = $etm->getStorage('group_content')->loadByProperties(['entity_id' => $old->id()]);
    foreach ($rels as $rel) {
      if (strpos($rel->getRelationshipType()->getPluginId(), 'group_node:') !== 0) {
        continue;
      }
      $existing = $etm->getStorage('group_content')->loadByProperties([
        'entity_id' => $node->id(),
        'gid' => $rel->getGroup()->id(),
        'type' => $rel->bundle(),
      ]);
      if (!$existing) {
        $etm->getStorage('group_content')->create([
          'type' => $rel->bundle(),
          'gid' => $rel->getGroup()->id(),
          'entity_id' => $node->id(),
          'label' => $node->label(),
        ])->save();
        print "    + group placement: group " . $rel->getGroup()->id() . "\n";
      }
    }
    // Menu links that target the old node follow the redesign.
    foreach ($etm->getStorage('menu_link_content')->loadByProperties(['link.uri' => 'entity:node/' . $old->id()]) as $link) {
      $link->set('link', ['uri' => 'entity:node/' . $node->id()] + $link->get('link')->first()->getValue());
      $link->save();
      print "    ~ menu link '" . $link->getTitle() . "' -> node " . $node->id() . "\n";
    }
    // The D7 original steps aside.
    if ($old->isPublished()) {
      $old->setUnpublished();
      $old->save();
      print "    ~ unpublished old node " . $old->id() . "\n";
    }
  }

  // The redesigned Home is the agsci front page. Per-domain fronts live in
  // domain_config's config COLLECTIONS (populated from D7 domain_conf by
  // the d7_domain migration) — the migrated agsci row points at the old D7
  // home node, so repoint that same row; subsite domains keep theirs.
  if ($n['stage_nid'] === 230866 && $node) {
    $storage = \Drupal::service('config.storage')->createCollection('domain.agsci_oregonstate_edu');
    $data = $storage->read('system.site') ?: [];
    if (($data['page']['front'] ?? '') !== '/node/' . $node->id()) {
      $data['page']['front'] = '/node/' . $node->id();
      $storage->write('system.site', $data);
      print "    ~ agsci domain front -> /node/" . $node->id() . "\n";
    }
  }
}
print "done.\n";
