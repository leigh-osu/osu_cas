<?php

/**
 * @file
 * Import per-domain config overrides (config collections) from config_imports.
 *
 * The Domain Config module stores a domain's configuration overrides in a
 * config collection named "domain.<domain_id>" (e.g. the front page for
 * bee.oregonstate.edu lives in collection "domain.bee_oregonstate_edu",
 * config object "system.site"). On disk these collections are nested
 * subdirectories: config_imports/domain/<domain_id>/<config_name>.yml
 *
 * `drush config:import --partial --source=...` only reads the DEFAULT
 * collection (the top-level *.yml files) and silently ignores collection
 * subdirectories, so it cannot import these overrides. This script writes each
 * file into its matching config collection in the active storage -- the same
 * thing a full `drush config:import` does internally, but scoped to just the
 * domain overrides so it can run late in a migration rebuild without touching
 * anything else.
 *
 * Idempotent: re-running simply rewrites the same collection config. Requires
 * the domain_config module to be enabled for the overrides to take effect at
 * runtime.
 *
 * KEEP IN SYNC: config_imports/domain/ is a hand-maintained copy of the
 * canonical export config/agsci-oregonstate-edu/domain/ (same convention as the
 * other config_imports/ subdirectories). Whenever the canonical domain
 * overrides change (e.g. after `drush cex`), re-copy them:
 *   rm -rf config_imports/domain
 *   cp -R config/agsci-oregonstate-edu/domain config_imports/domain
 *
 * Usage:
 *   ddev drush scr scripts-dev/import_domain_config.php
 */

use Drupal\Core\Serialization\Yaml;

// config_imports lives at the project root, one level above DRUPAL_ROOT.
$dir = DRUPAL_ROOT . '/../config_imports/domain';

if (!is_dir($dir)) {
  echo "Domain config directory not found: {$dir}\n";
  return;
}

/** @var \Drupal\Core\Config\StorageInterface $active */
$active = \Drupal::service('config.storage');

$domains = 0;
$objects = 0;
foreach (new \DirectoryIterator($dir) as $entry) {
  if ($entry->isDot() || !$entry->isDir()) {
    continue;
  }
  $domain_id = $entry->getFilename();
  $collection = $active->createCollection('domain.' . $domain_id);

  $files = glob($entry->getPathname() . '/*.yml');
  if (empty($files)) {
    continue;
  }
  $domains++;
  foreach ($files as $file) {
    $name = basename($file, '.yml');
    $data = Yaml::decode(file_get_contents($file));

    // Front-page guard. The agsci file carries the D7 front (the old Home
    // node); during a rebuild that is correct input, because
    // import_stage_redesigns.php runs later and repoints it at the
    // redesigned Home (whose nid is fresh each rebuild, so no file can
    // carry it). But an AD-HOC rerun of this script must not regress a
    // live front to an unpublished/missing node — that is how every
    // environment's front page 403'd on 2026-08-14. Keep the existing
    // front whenever it is healthier than the incoming one.
    if ($name === 'system.site' && !empty($data['page']['front'])) {
      $existing = $collection->read($name);
      $existing_front = $existing['page']['front'] ?? NULL;
      $resolve = function (?string $front): ?bool {
        // TRUE = published node, FALSE = unpublished/missing, NULL = not a node path.
        if (!$front || !preg_match('~^/node/(\d+)$~', $front, $m)) {
          return NULL;
        }
        $node = \Drupal\node\Entity\Node::load($m[1]);
        return $node ? $node->isPublished() : FALSE;
      };
      if ($resolve($data['page']['front']) === FALSE && $resolve($existing_front) === TRUE) {
        echo "domain.{$domain_id}: {$name} — keeping live front {$existing_front} (file's {$data['page']['front']} is unpublished/missing)\n";
        $data['page']['front'] = $existing_front;
      }
    }

    $collection->write($name, $data);
    $objects++;
    echo "domain.{$domain_id}: {$name}\n";
  }
}

echo "Imported {$objects} config override(s) across {$domains} domain(s).\n";