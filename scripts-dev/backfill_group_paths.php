<?php

/**
 * @file
 * Backfills Group Path and Group Short Name on basic_group groups.
 *
 * Both fields became required in October 2026. Group Path restores the alias
 * folder D7 kept in field_short_name, which the migration turned into a
 * display label. Only empty fields are filled; a value already entered is
 * never touched.
 *
 * Group Path, first source that applies:
 *   1. D7 field_short_name (embedded below: prod has no D7 database), with
 *      underscores removed and lowercased -- what D7 pathauto did, so the
 *      value matches the folder existing aliases already use.
 *   2. The last segment of the group home page's alias.
 *   3. The group title.
 * Group Short Name: the group title.
 *
 * Dry run by default; prints one row per group it would change.
 *
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/backfill_group_paths.php
 *   ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/backfill_group_paths.php -- --apply
 */

use Drupal\group\Entity\GroupInterface;

$apply = in_array('--apply', $extra ?? [], TRUE);

// D7 node nid => field_short_name, for every D7 group that had one (177).
// D10 group ids are the D7 nids.
$d7_short_names = [
  8 => 'main',
  39 => 'sorec',
  44 => 'woodhall',
  50 => 'lewis-brown',
  72 => 'hyslop',
  140 => 'aecs',
  159 => 'tradeoffs',
  165 => 'brr',
  170 => 'greenhouse',
  180 => 'cropandsoil',
  184 => 'bpp',
  206 => 'FEPP',
  213 => 'leadership_academy',
  227 => 'leadership_minor',
  3960 => 'botany-field-labs',
  3965 => 'vegetable-farm',
  3986 => 'hyslop-weather',
  4036 => 'art',
  11895 => 'fwcs',
  11910 => 'ambassadors',
  11923 => 'clubs-orgs',
  11955 => 'research',
  16023 => 'foodsci',
  16025 => 'emt',
  16031 => 'appliedecon',
  16033 => 'entomology',
  16035 => 'smallfarms',
  16067 => 'wheat',
  16130 => 'webteam',
  16804 => 'manrrs',
  16830 => 'environmental_chem',
  16844 => 'ag_program_eou',
  16907 => 'lmrcsc',
  24265 => 'agbiotech',
  24278 => 'ruralstudies',
  25338 => 'thesource',
  25345 => 'bee',
  25694 => 'sacnas',
  25814 => 'owri',
  25816 => 'plantbreeding',
  26046 => 'bioenergy-education',
  26239 => 'bioenergy-k-12',
  26292 => 'wrcefs',
  26361 => 'slug-portal',
  26735 => 'comes',
  27355 => 'opens',
  29306 => 'eisi',
  36671 => 'bmsb',
  38376 => 'our-best',
  43971 => 'fec-lab',
  44491 => 'vt',
  45001 => 'mes',
  45851 => 'fw-gs-guide',
  46636 => 'psep',
  47186 => 'nursery',
  56709 => 'oilseed-fiber-crops',
  56711 => 'acg',
  56715 => 'crop-production',
  56721 => 'potatoes',
  61206 => 'state-fisheries-lab',
  61296 => 'oipmc-old',
  62561 => 'roots',
  63406 => 'flock',
  63906 => 'residentialbeekeeper',
  63931 => 'education',
  67286 => 'osuseafoodlab',
  67681 => 'oregon-vegetables',
  67691 => 'permaculture',
  67701 => 'OCCUH',
  67711 => 'tree-fruits-and-nuts',
  67761 => 'beaverturf',
  67781 => 'horticulture',
  67791 => 'berrycrops',
  82276 => 'seafood-network',
  85166 => 'infews',
  86246 => 'ospud',
  86926 => 'research-symposium',
  87151 => 'cbarc',
  87271 => 'faces-agsci',
  87326 => 'eoarc',
  87336 => 'eoarcunion',
  89321 => 'foodweb',
  97316 => 'isb',
  99036 => 'coarec',
  107916 => 'plant-clinic',
  108536 => 'bpp-gsa',
  108636 => 'herbarium',
  109786 => 'harec',
  109796 => 'kbrec',
  109806 => 'mcarec',
  109816 => 'nwrec',
  109891 => 'newsroom',
  110146 => 'cas-communications',
  110821 => 'mi',
  110936 => 'mes-weather',
  112531 => 'petersonsymposium',
  113061 => 'hemp',
  113161 => 'organic_grad_cert',
  113326 => 'vining-lab',
  113841 => 'endophyte-lab',
  122381 => 'kerkvlietsymposium',
  125816 => 'parks-lab',
  125906 => 'western-center-dce',
  167216 => 'anrs',
  211056 => 'comes2',
  211281 => 'chapple-lab',
  214126 => 'bds',
  217136 => '2020iyph',
  217306 => 'shl',
  218366 => 'marcus-annalora-lab',
  218401 => 'buhloutreachteam',
  218441 => 'jenniferduringerlab',
  218481 => 'stacisimonichlab',
  218521 => 'jenniferfieldlab',
  220016 => 'stap',
  224256 => 'ornithology',
  224266 => 'ichthyology',
  224531 => 'centerforresilient',
  225236 => 'Listeria-HELPR',
  226111 => 'owqda',
  226236 => 'mycas',
  228631 => 'home',
  229166 => 'sustainability',
  229686 => 'fic',
  232571 => 'emt-gs-guide',
  232901 => 'efb',
  233091 => 'nurspest',
  235971 => 'oregonbeeproject',
  236056 => 'sungrant',
  238471 => 'letitiacarson',
  239376 => 'garcia-jaramillolab',
  239546 => 'grapesmokeexposure',
  239556 => 'winesensory',
  241591 => 'bee2',
  248306 => 'schmidt-lab',
  250396 => 'international',
  250631 => 'tek',
  251411 => 'specialtyfood',
  251541 => 'berryhealth',
  252661 => 'levinlab',
  253231 => 'cabbage-maggot',
  253661 => 'hort-dev',
  254111 => 'campusarb',
  254821 => 'sulikowskilab',
  256241 => 'curtin-lab',
  256531 => 'climate-smart-potato',
  256681 => 'heppelllab',
  257116 => 'weed-science-hort',
  257531 => 'dairy-center',
  258266 => 'hort_graduate',
  259201 => 'myfwcs',
  259326 => 'gardenecology',
  259576 => 'oipmc',
  260246 => 'er-jackman',
  261631 => 'wvbs',
  265476 => 'marine-social-sci',
  265611 => 'agrivoltaics',
  265786 => 'beecampus',
  266621 => 'vegweedsci',
  268316 => 'barleyworld',
  269111 => 'seabird-oceanography',
  270891 => 'fieldcropweeds',
  270951 => 'valleyfieldcrops',
  273116 => 'humandimensions',
  273436 => 'bee-atlas',
  273851 => 'beaverlandscapes',
  282016 => 'fungicidebooklet',
  282511 => 'CormOceanProject',
  282671 => 'soilwaterquality',
  284226 => 'juliayuecuilab',
  285231 => 'moretti-lab',
  286081 => 'ourwillamette',
  286481 => 'digital-ag',
  286591 => 'climate-emotions',
  286676 => 'HarvesterCAP',
  287831 => '100fish',
  288076 => 'rohlman',
];

$slug = static function (string $value): string {
  $value = strtolower(str_replace('_', '', $value));
  return trim(preg_replace('/[^a-z0-9]+/', '-', $value), '-');
};

$storage = \Drupal::entityTypeManager()->getStorage('group');
$alias_manager = \Drupal::service('path_alias.manager');
$pattern = '/^' . OSU_CAS_GROUP_PATH_PATTERN . '$/';

/** @var \Drupal\group\Entity\GroupInterface[] $groups */
$groups = $storage->loadByProperties(['type' => 'basic_group']);
ksort($groups);

// Paths already held, so planned values never collide with entered ones.
$taken = [];
foreach ($groups as $gid => $group) {
  $value = (string) $group->get('field_group_path')->value;
  if ($value !== '') {
    $taken[$value] = $gid;
  }
}

$plan = [];
$problems = [];
foreach ($groups as $gid => $group) {
  $changes = [];

  if ($group->get('field_group_path')->isEmpty()) {
    $path = '';
    $source = '';
    if (isset($d7_short_names[$gid])) {
      $path = $slug($d7_short_names[$gid]);
      $source = 'd7:' . $d7_short_names[$gid];
    }
    if ($path === '' && !$group->get('field_group_home_page')->isEmpty()) {
      $alias = $alias_manager->getAliasByPath('/node/' . $group->get('field_group_home_page')->target_id);
      if (!str_starts_with($alias, '/node/')) {
        $parts = explode('/', trim($alias, '/'));
        $path = $slug(end($parts));
        $source = 'home:' . $alias;
      }
    }
    if ($path === '') {
      $path = $slug((string) $group->label());
      $source = 'title';
    }

    if (!preg_match($pattern, $path)) {
      $problems[] = "$gid {$group->label()}: derived path '$path' ($source) fails the pattern";
    }
    elseif (isset($taken[$path])) {
      $problems[] = "$gid {$group->label()}: path '$path' ($source) already belongs to group {$taken[$path]}";
    }
    else {
      $taken[$path] = $gid;
      $changes['field_group_path'] = [$path, $source];
    }
  }

  if ($group->get('field_group_short_name')->isEmpty() || trim((string) $group->get('field_group_short_name')->value) === '') {
    $changes['field_group_short_name'] = [trim((string) $group->label()), 'title'];
  }

  if ($changes) {
    $plan[$gid] = $changes;
  }
}

foreach ($plan as $gid => $changes) {
  foreach ($changes as $field => [$value, $source]) {
    printf("%-7d %-24s %-45s %s\n", $gid, $field, $value, $source);
  }
}
foreach ($problems as $problem) {
  print "SKIP $problem\n";
}
printf("\n%d groups, %d to change, %d skipped. %s\n", count($groups), count($plan), count($problems), $apply ? 'Applying.' : 'Dry run: add -- --apply to write.');

if (!$apply) {
  return;
}

$saved = 0;
foreach ($plan as $gid => $changes) {
  $group = $storage->load($gid);
  foreach ($changes as $field => [$value]) {
    $group->set($field, $value);
  }
  $violations = $group->validate()->filterByFields(array_diff(array_keys($group->getFields()), array_keys($changes)));
  if ($violations->count()) {
    foreach ($violations as $violation) {
      printf("FAIL %d %s: %s\n", $gid, $violation->getPropertyPath(), strip_tags((string) $violation->getMessage()));
    }
    continue;
  }
  // Data correction, not an edit: no new revision, and the changed time is put
  // back afterwards. ChangedItem bumps it on any field change unless it is set
  // to a different value, so it cannot be held in place before the save.
  $changed = $group->getChangedTime();
  $group->setSyncing(TRUE);
  $group->save();
  $database = \Drupal::database();
  $database->update('groups_field_data')
    ->fields(['changed' => $changed])
    ->condition('id', $gid)
    ->execute();
  $database->update('groups_field_revision')
    ->fields(['changed' => $changed])
    ->condition('id', $gid)
    ->condition('revision_id', $group->getRevisionId())
    ->execute();
  $saved++;
}
$storage->resetCache();
printf("Saved %d of %d.\n", $saved, count($plan));
