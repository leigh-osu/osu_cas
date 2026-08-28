<?php
// Remove orphaned dev-module schema entries carried in by the DB push.
$modules = ['devel', 'migrate_devel', 'devel_generate', 'webprofiler'];
$deleted = \Drupal::database()->delete('key_value')
  ->condition('collection', 'system.schema')
  ->condition('name', $modules, 'IN')
  ->execute();
printf("  removed %d system.schema entries\n", $deleted);
// And any leftover config those modules owned.
$removed = 0;
foreach (\Drupal::configFactory()->listAll('devel.') as $name) {
  \Drupal::configFactory()->getEditable($name)->delete();
  $removed++;
}
printf("  removed %d devel.* config objects\n", $removed);
