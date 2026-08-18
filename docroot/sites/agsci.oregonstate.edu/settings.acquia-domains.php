<?php

/**
 * @file
 * Environment-aware Domain hostnames for Acquia dev and stage.
 *
 * The domain.record.* config carries the PRODUCTION hostnames. Locally,
 * settings.local.php (untracked) rewrites them to ddev.-prefixed hosts; this
 * tracked include does the same for the Acquia non-prod environments so
 * Domain negotiation matches the hostnames sites.php serves there:
 *   X.oregonstate.edu -> X.<env>.oregonstate.edu
 *   Y.org             -> <env>.Y.org
 * Production needs no rewrite. Regenerate the map with:
 *   for f in config/agsci.oregonstate.edu/domain.record.*.yml; do \
 *     printf "  '%s' => '%s',\n" "$(grep '^id:' "$f" | awk '{print $2}')" \
 *       "$(grep '^hostname:' "$f" | awk '{print $2}')"; done | sort
 */

$ah_env = $_ENV['AH_SITE_ENVIRONMENT'] ?? '';
// LAUNCH: 'prod' is in this list only until cutover. While the real hostnames
// point elsewhere, the prod environment is reached via x.prod.oregonstate.edu
// / prod.x.org and Domain negotiation must match those. When the real
// hostnames move to Acquia prod, remove 'prod' here so the records keep
// their production hostnames.
if (in_array($ah_env, ['dev', 'stage', 'prod'], TRUE)) {
  $osu_cas_domain_records = [
  'agbiotech_oregonstate_edu' => 'agbiotech.oregonstate.edu',
  'agsci_oregonstate_edu' => 'agsci.oregonstate.edu',
  'anrs_oregonstate_edu' => 'anrs.oregonstate.edu',
  'appliedecon_oregonstate_edu_' => 'appliedecon.oregonstate.edu',
  'bee_oregonstate_edu' => 'bee.oregonstate.edu',
  'beecampus_oregonstate_edu' => 'beecampus.oregonstate.edu',
  'bpp_oregonstate_edu' => 'bpp.oregonstate.edu',
  'campusarb_oregonstate_edu' => 'campusarb.oregonstate.edu',
  'centerforsmallfarms_oregonstate_edu' => 'crafs.oregonstate.edu',
  'cropandsoil_oregonstate_edu' => 'cropandsoil.oregonstate.edu',
  'emt_oregonstate_edu' => 'emt.oregonstate.edu',
  'entomology_oregonstate_edu' => 'entomology.oregonstate.edu',
  'fic_oregonstate_edu' => 'fic.oregonstate.edu',
  'foodsci_oregonstate_edu' => 'foodsci.oregonstate.edu',
  'fw_oregonstate_edu' => 'fwcs.oregonstate.edu',
  'gardenecology_oregonstate_edu' => 'gardenecology.oregonstate.edu',
  'honeybeelab_oregonstate_edu_' => 'honeybeelab.oregonstate.edu',
  'horticulture_oregonstate_edu' => 'horticulture.oregonstate.edu',
  'http_marineresearch_oregonstate_edu' => 'marineresearch.oregonstate.edu',
  'ichthyology_oregonstate_edu' => 'ichthyology.oregonstate.edu',
  'infews_org' => 'infews.org',
  'letitiacarson_oregonstate_edu' => 'letitiacarson.oregonstate.edu',
  'open_sensing_org' => 'open-sensing.org',
  'osuseafoodlab_oregonstate_edu' => 'osuseafoodlab.oregonstate.edu',
  'ourwillamette_oregonstate_edu' => 'ourwillamette.oregonstate.edu',
  'owri_oregonstate_edu' => 'owri.oregonstate.edu',
  'plantbreeding_oregonstate_edu_' => 'plantbreeding.oregonstate.edu',
  'ruralstudies_oregonstate_edu' => 'ruralstudies.oregonstate.edu',
  'seafood_oregonstate_edu' => 'seafood.oregonstate.edu',
  'smallfarms_oregonstate_edu' => 'smallfarms.oregonstate.edu',
  'soilwaterquality_oregonstate_edu' => 'soilwaterquality.oregonstate.edu',
  'spottedwing_org' => 'spottedwing.org',
  'sungrant_oregonstate_edu' => 'sungrant.oregonstate.edu',
  'support_roots_oregonstate_edu' => 'support.roots.oregonstate.edu',
  'tradeoffs_oregonstate_edu' => 'tradeoffs.oregonstate.edu',
  ];
  foreach ($osu_cas_domain_records as $osu_cas_record_id => $osu_cas_hostname) {
    if (str_ends_with($osu_cas_hostname, '.oregonstate.edu')) {
      $osu_cas_env_hostname = substr($osu_cas_hostname, 0, -strlen('.oregonstate.edu')) . ".$ah_env.oregonstate.edu";
    }
    else {
      $osu_cas_env_hostname = "$ah_env.$osu_cas_hostname";
    }
    $config["domain.record.$osu_cas_record_id"]['hostname'] = $osu_cas_env_hostname;
  }
}
