<?php

/**
 * @file
 * Creates the mmi.oregonstate.edu Domain record if it does not exist.
 *
 * Idempotent; run via scripts-dev/mmi_migrate.sh section 4. The record must
 * exist before mmi_users runs (field_domain_access on created accounts) and
 * before any content constants reference it. The record is also in the repo
 * config export (domain.record.mmi_oregonstate_edu), so after a config
 * import this is a no-op; domain_id and weight here match that file
 * (domain_id is the Domain module's crc32 hash of the record id).
 */

$storage = \Drupal::entityTypeManager()->getStorage('domain');

if ($storage->load('mmi_oregonstate_edu')) {
  print "domain.record.mmi_oregonstate_edu already exists\n";
  return;
}

$storage->create([
  'id' => 'mmi_oregonstate_edu',
  'domain_id' => 14341146,
  'hostname' => 'mmi.oregonstate.edu',
  'name' => 'Marine Mammal Institute',
  'scheme' => 'https',
  'status' => TRUE,
  'weight' => 45,
  'is_default' => FALSE,
  'path_prefix' => '',
])->save();

print "created domain.record.mmi_oregonstate_edu (domain_id 14341146)\n";
