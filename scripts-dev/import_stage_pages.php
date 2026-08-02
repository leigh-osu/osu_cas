<?php

/**
 * @file
 * Overlay the stage-authored Home (99701) and Education (231201) pages onto
 * this environment from the default_content export in
 * scripts-dev/dc_stage_pages (produced on stage with drush dcer).
 *
 * Creates the stage-authored inline blocks and their media/file/taxonomy
 * dependencies by UUID (create-if-missing), then updates the two nodes'
 * fields and Layout Builder sections, remapping each section component's
 * block_id/block_revision_id from stage ids to the local ids via
 * block_id_map.json. Users, groups and group_content from the export are
 * deliberately NOT imported (the rebuild provides them); dangling
 * references to them are dropped.
 *
 * Idempotent — run after every rebuild:
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/import_stage_pages.php
 */

use Drupal\Core\Serialization\Yaml;
use Drupal\layout_builder\Section;

$dir = DRUPAL_ROOT . '/../scripts-dev/dc_stage_pages';
$etm = \Drupal::entityTypeManager();

// The two target pages: stage uuid => local nid.
$pages = [
  '6bd6aceb-d606-4981-af40-3b67371c6e27' => 99701,
  'dd6c34e8-f1be-4eb7-9c0c-3d6819498d56' => 231201,
];

// Dependency types we import; everything else resolves-or-drops.
$importable = ['file', 'media', 'taxonomy_term', 'block_content'];
// Entity-level properties never overlaid onto existing content.
$skip_fields = ['uid', 'revision_uid', 'revision_user', 'created', 'changed', 'revision_created', 'revision_log', 'revision_log_message'];

$map = json_decode(file_get_contents("$dir/block_id_map.json"), TRUE);
$uuid_by_stage_rev = [];
foreach ($map as $stage_rev => $info) {
  $uuid_by_stage_rev[$stage_rev] = $info['uuid'];
}

$by_uuid = function (string $type, string $uuid) use ($etm) {
  $found = $etm->getStorage($type)->loadByProperties(['uuid' => $uuid]);
  return $found ? reset($found) : NULL;
};

$load_yml = function (string $type, string $uuid) use ($dir) {
  $f = "$dir/$type/$uuid.yml";
  return file_exists($f) ? Yaml::decode(file_get_contents($f)) : NULL;
};

/**
 * Swap "entity: <uuid>" reference items for local target_ids; drop dangling.
 */
$resolve_fields = function (array $values, array $depends) use ($by_uuid) {
  $out = [];
  foreach ($values as $field => $items) {
    if (!is_array($items)) {
      $out[$field] = $items;
      continue;
    }
    $resolved = [];
    foreach ($items as $item) {
      if (is_array($item) && isset($item['entity']) && is_string($item['entity'])) {
        $type = $depends[$item['entity']] ?? NULL;
        $target = $type ? $by_uuid($type, $item['entity']) : NULL;
        if (!$target) {
          continue;
        }
        $item['target_id'] = $target->id();
        unset($item['entity']);
      }
      $resolved[] = $item;
    }
    $out[$field] = $resolved;
  }
  return $out;
};

/**
 * Create an exported entity (and its importable deps) if its UUID is absent.
 */
$import_entity = function (string $type, string $uuid) use (&$import_entity, $etm, $by_uuid, $load_yml, $resolve_fields, $importable, $skip_fields) {
  if ($existing = $by_uuid($type, $uuid)) {
    return $existing;
  }
  $yml = $load_yml($type, $uuid);
  if (!$yml) {
    print "  ! no export for $type $uuid\n";
    return NULL;
  }
  $depends = $yml['_meta']['depends'] ?? [];
  foreach ($depends as $dep_uuid => $dep_type) {
    if (in_array($dep_type, $importable, TRUE)) {
      $import_entity($dep_type, $dep_uuid);
    }
  }
  $values = $resolve_fields(array_diff_key($yml['default'], array_flip($skip_fields)), $depends);
  $def = $etm->getDefinition($type);
  if ($bundle_key = $def->getKey('bundle')) {
    $values[$bundle_key] = $yml['_meta']['bundle'];
  }
  $values['uuid'] = $uuid;
  $entity = $etm->getStorage($type)->create($values);
  $entity->save();
  if ($type === 'file' && !file_exists(\Drupal::service('file_system')->realpath($entity->getFileUri()) ?: '')) {
    print "  ! file entity created but binary missing: " . $entity->getFileUri() . "\n";
  }
  print "  + created $type " . $entity->id() . " ($uuid)\n";
  return $entity;
};

foreach ($pages as $uuid => $nid) {
  $yml = $load_yml('node', $uuid);
  if (!$yml) {
    print "MISSING export for node $uuid\n";
    continue;
  }
  $node = $by_uuid('node', $uuid) ?: \Drupal\node\Entity\Node::load($nid);
  if (!$node) {
    print "No local node for $uuid / $nid — create it via migration first.\n";
    continue;
  }
  print "== node {$node->id()} ({$yml['default']['title'][0]['value']})\n";
  $depends = $yml['_meta']['depends'] ?? [];

  // Ensure importable dependencies exist (inline blocks, media, files).
  foreach ($depends as $dep_uuid => $dep_type) {
    if (in_array($dep_type, $importable, TRUE)) {
      $import_entity($dep_type, $dep_uuid);
    }
  }

  // Overlay simple fields.
  $values = $resolve_fields(array_diff_key($yml['default'], array_flip(array_merge($skip_fields, ['layout_builder__layout']))), $depends);
  foreach ($values as $field => $items) {
    if ($node->hasField($field)) {
      $node->set($field, $items);
    }
  }

  // Rebuild sections, remapping inline block ids stage -> local.
  if (!empty($yml['default']['layout_builder__layout'])) {
    $sections = [];
    foreach ($yml['default']['layout_builder__layout'] as $item) {
      $section = $item['section'];
      foreach ($section['components'] ?? [] as $ci => $component) {
        $cfg = $component['configuration'] ?? [];
        if (str_starts_with($cfg['id'] ?? '', 'inline_block:')) {
          $stage_rev = (string) ($cfg['block_revision_id'] ?? '');
          $block_uuid = $uuid_by_stage_rev[$stage_rev] ?? NULL;
          $block = $block_uuid ? $by_uuid('block_content', $block_uuid) : NULL;
          if ($block) {
            $section['components'][$ci]['configuration']['block_id'] = $block->id();
            $section['components'][$ci]['configuration']['block_revision_id'] = $block->getRevisionId();
          }
          else {
            print "  ! dropping component with unmapped stage block rev $stage_rev\n";
            unset($section['components'][$ci]);
          }
        }
      }
      $sections[] = Section::fromArray($section);
    }
    $node->set('layout_builder__layout', $sections);
  }

  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Overlay from stage export (import_stage_pages.php)');
  $node->save();
  print "  saved.\n";
}

print "Done.\n";
