<?php

/**
 * @file
 * Creates the mmi.oregonstate.edu Domain record if it does not exist.
 *
 * Idempotent; run via scripts-dev/mmi_migrate.sh section 4. The record must
 * exist before mmi_users runs (field_domain_access on created accounts) and
 * before any content constants reference it. domain_id follows the Domain
 * module's max+1 convention.
 */

$storage = \Drupal::entityTypeManager()->getStorage('domain');

if ($storage->load('mmi_oregonstate_edu')) {
  print "domain.record.mmi_oregonstate_edu already exists\n";
  return;
}

$max = 0;
foreach ($storage->loadMultiple() as $domain) {
  $max = max($max, (int) $domain->getDomainId());
}

$storage->create([
  'id' => 'mmi_oregonstate_edu',
  'domain_id' => $max + 1,
  'hostname' => 'mmi.oregonstate.edu',
  'name' => 'Marine Mammal Institute',
  'scheme' => 'https',
  'status' => TRUE,
  'weight' => 100,
  'is_default' => FALSE,
  'path_prefix' => '',
])->save();

print "created domain.record.mmi_oregonstate_edu (domain_id " . ($max + 1) . ")\n";
