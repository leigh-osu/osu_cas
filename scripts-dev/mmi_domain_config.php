<?php

/**
 * @file
 * Per-domain config collection for the mmi domain (name + front page).
 *
 * Domain-specific site settings live in config COLLECTIONS
 * (domain.<id>:system.site), the D10 home of D7's domain_conf — they
 * override both global config and domain.config.* objects. The front page
 * is the migrated D7 front (node 3 "home" -> 400003). Idempotent; run in
 * mmi_migrate.sh section 8 after the nodes exist.
 */

use Drupal\osu_migrations_mmi\Plugin\migrate\process\MmiNidOffset;

$collection = \Drupal::service('config.storage')->createCollection('domain.mmi_oregonstate_edu');
$existing = $collection->read('system.site') ?: [];

$desired = [
  'name' => 'Marine Mammal Institute',
  'slogan' => '',
  'mail' => 'noreply@mail.drupal.oregonstate.edu',
  'page' => [
    '403' => '',
    '404' => '',
    'front' => '/node/' . (3 + MmiNidOffset::OFFSET),
  ],
];

if ($existing == $desired) {
  print "domain.mmi_oregonstate_edu:system.site already in place\n";
  return;
}

$collection->write('system.site', $desired);
\Drupal::service('cache.config')->deleteAll();
print "wrote domain.mmi_oregonstate_edu:system.site (front /node/" . (3 + MmiNidOffset::OFFSET) . ")\n";
