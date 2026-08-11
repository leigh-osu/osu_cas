<?php

/**
 * @file
 * Recreate the migration-development sitewide alert.
 *
 * The rebuild wipes the database, and this banner is the thing that stops
 * someone mistaking the local/dev site for somewhere their edits survive. It
 * is content rather than config (sitewide_alert is a content entity), so
 * config:import cannot carry it and it has to be recreated here.
 *
 * The companion settings live in config_imports/sitewide_alert.settings.yml --
 * show_on_admin is on, so the banner is visible on admin pages too, which is
 * where the mistake would actually be made.
 *
 * Keyed by UUID so it is idempotent: re-running updates the existing alert
 * rather than stacking duplicates.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/create_sitewide_alert.php
 */

const CAS_DEV_ALERT_UUID = '54cd1752-24ab-4d8e-bfa5-27aa401bbcda';

$storage = \Drupal::entityTypeManager()->getStorage('sitewide_alert');

$existing = $storage->loadByProperties(['uuid' => CAS_DEV_ALERT_UUID]);
$alert = $existing ? reset($existing) : $storage->create(['uuid' => CAS_DEV_ALERT_UUID]);

$alert->set('name', 'MIGRATION DEVELOPMENT');
$alert->set('style', 'osu-danger');
$alert->set('dismissible', 0);
$alert->set('dismissible_ignore_before_time', 0);
$alert->set('scheduled_alert', 0);
$alert->set('limit_to_pages_negate', 0);
// text_and_links, matching how the alert was authored: the banner needs no
// more than a sentence and a link, and full_html here would let anything
// pasted into it render.
$alert->set('message', [
  'value' => '<p>Changes will NOT be saved permanently. Database and files will be overwritten!</p>' . "\r\n",
  'format' => 'text_and_links',
]);
$alert->setPublished();
$alert->save();

printf("%s sitewide alert %s (%s)\n",
  $existing ? 'Updated' : 'Created', $alert->id(), $alert->label());
