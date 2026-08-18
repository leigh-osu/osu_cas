<?php

/**
 * @file
 * Repairs block_visibility_group config entities with a null boolean.
 *
 * block_visibility_groups 2.0.5 (composer update, 18 Aug 2026) typed the
 * entity property:
 *
 *   protected bool $allow_other_conditions = FALSE;
 *
 * PHP cannot assign NULL to a typed bool, so every group carrying a null --
 * 174 of the 175 here, all of them from the migration -- became a fatal
 * TypeError in EntityBase::__construct(). Anything that loads the full set
 * dies, /admin/structure/block included. On 2.0.4 the untyped property took
 * the null quietly, which is why this surfaced only after the update.
 *
 * FALSE is the right value: it is the class default, it matches the one group
 * created through the D10 UI (site_front_page), and the schema types the key
 * as boolean.
 *
 * The entity API cannot be used to fix this -- loading the entity is the thing
 * that throws -- so this writes through the config factory instead.
 *
 * Usage:
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/fix_block_visibility_group_bools.php
 */

$config_factory = \Drupal::configFactory();
$names = $config_factory->listAll('block_visibility_groups.block_visibility_group.');

$fixed = 0;
$already = 0;
$examples = [];

foreach ($names as $name) {
  $config = $config_factory->getEditable($name);
  $value = $config->get('allow_other_conditions');

  if (is_bool($value)) {
    $already++;
    continue;
  }

  // Preserve any truthy intent rather than flattening everything to FALSE;
  // in practice these are all null, but a stray 0/1 should survive as itself.
  $config->set('allow_other_conditions', (bool) $value)->save();
  $fixed++;
  if (count($examples) < 3) {
    $examples[] = str_replace('block_visibility_groups.block_visibility_group.', '', $name);
  }
}

print "groups examined: " . count($names) . "\n";
print "  repaired:      $fixed\n";
print "  already bool:  $already\n";
if ($examples) {
  print "  e.g. " . implode(', ', $examples) . "\n";
}

// Prove the fix: loading every entity is exactly what was fatal before.
try {
  $storage = \Drupal::entityTypeManager()->getStorage('block_visibility_group');
  $storage->resetCache();
  $loaded = $storage->loadMultiple();
  print "verification: loaded " . count($loaded) . " entities without error\n";
}
catch (\Throwable $e) {
  print "verification FAILED: " . get_class($e) . ': ' . $e->getMessage() . "\n";
}
