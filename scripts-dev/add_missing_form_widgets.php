<?php

/**
 * @file
 * Adds field widgets that exist in config but are missing from live node
 * edit forms, then refreshes the config_imports/display copies.
 *
 * Migrated types ended up with fields configured but hidden on the edit
 * forms (both the full export and the config_imports partials list them
 * under 'hidden'): editors could not see Domain Access / affiliates /
 * Domain Source on most types, the taxonomy topic fields on video, or
 * Flowers/Stem on weed. Components are cloned from live donor displays
 * (page for the domain trio, story for the taxonomy multiselects, weed's
 * own description textarea for its text fields) and the partial-import
 * files under config_imports/display are rewritten from the result so
 * rebuilds keep the widgets. Idempotent.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/add_missing_form_widgets.php
 */

use Drupal\Core\Serialization\Yaml;

$efd_storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');

// Donor components, cloned from displays that already show the field.
$page = $efd_storage->load('node.page.default');
$story = $efd_storage->load('node.story.default');
$weed = $efd_storage->load('node.weed.default');
$donor = [
  'field_domain_access' => $page->getComponent('field_domain_access'),
  'field_domain_all_affiliates' => $page->getComponent('field_domain_all_affiliates'),
  'field_domain_source' => $page->getComponent('field_domain_source'),
  'field_tax_coarec_topics' => $story->getComponent('field_tax_coarec_topics'),
  'field_tax_nursery_topics' => $story->getComponent('field_tax_nursery_topics'),
  'field_tax_owri_topics' => $story->getComponent('field_tax_owri_topics'),
  'field_tax_swd_topics' => $story->getComponent('field_tax_swd_topics'),
  'field_tax_turf_topics' => $story->getComponent('field_tax_turf_topics'),
  'field_tax_veg_topics' => $story->getComponent('field_tax_veg_topics'),
  'field_tax_work_area' => $story->getComponent('field_tax_work_area'),
  // No display shows these anywhere; reuse the closest sibling widget.
  'field_tax_event' => $story->getComponent('field_tax_veg_topics'),
  'field_weed_flowers' => $weed->getComponent('field_weed_brief_description'),
  'field_weed_stem' => $weed->getComponent('field_weed_brief_description'),
];

$domain_fields = ['field_domain_access', 'field_domain_all_affiliates', 'field_domain_source'];
$targets = [
  '150_species' => $domain_fields,
  'art_about_agriculture' => $domain_fields,
  'course' => $domain_fields,
  'enterprise_budgets' => $domain_fields,
  'fun_facts' => $domain_fields,
  'funding_opportunities' => $domain_fields,
  'image_album' => $domain_fields,
  'osu_profile' => $domain_fields,
  'plant_variety_release' => $domain_fields,
  'project' => $domain_fields,
  'story' => $domain_fields,
  'video' => array_merge($domain_fields, [
    'field_tax_coarec_topics', 'field_tax_event', 'field_tax_nursery_topics',
    'field_tax_owri_topics', 'field_tax_swd_topics', 'field_tax_turf_topics',
    'field_tax_veg_topics', 'field_tax_work_area',
  ]),
  'weather_daily_data' => $domain_fields,
  'weather_monthly_data' => $domain_fields,
  'weed' => array_merge($domain_fields, ['field_weed_flowers', 'field_weed_stem']),
];

$imports_dir = DRUPAL_ROOT . '/../config_imports/display';

foreach ($targets as $bundle => $fields) {
  $display = $efd_storage->load("node.$bundle.default");
  if (!$display) {
    print "$bundle: no live form display, skipped\n";
    continue;
  }
  $added = [];
  foreach ($fields as $field) {
    if ($display->getComponent($field) || empty($donor[$field])) {
      continue;
    }
    $display->setComponent($field, $donor[$field]);
    $added[] = $field;
  }
  if ($added) {
    $display->save();
    print "$bundle: added " . implode(', ', $added) . "\n";
  }
  else {
    print "$bundle: already complete\n";
  }
  $raw = \Drupal::config("core.entity_form_display.node.$bundle.default")->getRawData();
  unset($raw['_core']);
  file_put_contents("$imports_dir/core.entity_form_display.node.$bundle.default.yml", Yaml::encode($raw));
}
print "Done.\n";
