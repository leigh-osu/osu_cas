<?php

/**
 * @file
 * Create/update cas_career (Agricultural Companies) nodes from
 * cas_career_companies.json (built by cas_career_companies_build.py).
 *
 * Dry run (default):  drush scr scripts-dev/cas_career_import.php
 * Apply:              drush scr scripts-dev/cas_career_import.php -- apply
 *
 * Idempotent: nodes are matched by exact title; re-running only touches
 * nodes whose CSV fields changed. Body text is never overwritten. Every
 * cas_career node is added to the Department of Food Science and Technology
 * group (needs the basic_group cas_career group config imported first), and
 * that group's Content Settings get Career opportunity and its taxonomies.
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

$apply = in_array('apply', $extra ?? [], TRUE);
$domain = 'foodsci_oregonstate_edu';
echo $apply ? "APPLY\n" : "DRY RUN (pass '-- apply' to write)\n";

$data = json_decode(file_get_contents(__DIR__ . '/cas_career_companies.json'), TRUE);
if (!$data) {
  throw new \RuntimeException('Could not read cas_career_companies.json');
}
if (!\Drupal::entityTypeManager()->getStorage('domain')->load($domain)) {
  throw new \RuntimeException("Domain $domain not found");
}

// 1. Industry becomes multi-value (matches the exported field storage config).
$storage = FieldStorageConfig::loadByName('node', 'field_tax_cas_career_industry');
if ($storage->getCardinality() !== -1) {
  echo "industry field: cardinality {$storage->getCardinality()} -> unlimited\n";
  if ($apply) {
    $storage->setCardinality(-1)->save();
  }
}

$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$db = \Drupal::database();

// 2. The state vocabulary has Connecticut mislabelled as a second "Colorado".
$ct = $terms->loadByProperties(['vid' => 'state', 'name' => 'Connecticut']);
$co = $terms->loadByProperties(['vid' => 'state', 'name' => 'Colorado']);
if (!$ct && count($co) > 1) {
  ksort($co);
  $wrong = end($co);
  echo "state term {$wrong->id()}: rename 'Colorado' -> 'Connecticut'\n";
  if ($apply) {
    $wrong->setName('Connecticut')->save();
  }
}

// Term name -> tid, creating missing industry terms.
$lookup = function ($vid, $name, $create = FALSE) use ($db, $apply) {
  static $cache = [];
  $key = "$vid:$name";
  if (!isset($cache[$key])) {
    $tids = $db->query("SELECT tid FROM {taxonomy_term_field_data} WHERE vid = :vid AND name = :name ORDER BY tid", [':vid' => $vid, ':name' => $name])->fetchCol();
    if (!$tids && $create) {
      echo "create $vid term '$name'\n";
      if ($apply) {
        $term = Term::create(['vid' => $vid, 'name' => $name, 'langcode' => 'en']);
        $term->save();
        $tids = [$term->id()];
      }
      else {
        $tids = ["new:$name"];
      }
    }
    if (!$tids) {
      throw new \RuntimeException("No $vid term '$name'");
    }
    $cache[$key] = reset($tids);
  }
  return $cache[$key];
};
$state_tid = function ($name) use ($lookup, $apply, $ct, $co) {
  // In a dry run before the rename, Connecticut is still the second Colorado.
  if ($name === 'Connecticut' && !$apply && !$ct && count($co) > 1) {
    return 'connecticut';
  }
  return $lookup('state', $name);
};

$nodes = \Drupal::entityTypeManager()->getStorage('node');
$created = $updated = $unchanged = 0;
foreach ($data as $row) {
  $values = [
    'field_cas_career_city' => $row['city'] === '' ? [] : [['value' => $row['city']]],
    'field_cas_career_about_us' => $row['about'] === '' ? [] : [['uri' => $row['about'], 'title' => '']],
    'field_cas_career_careers' => $row['careers'] === '' ? [] : [['uri' => $row['careers'], 'title' => '']],
    'field_tax_cas_career_industry' => array_map(fn($n) => ['target_id' => $lookup('cas_career_industry', $n, TRUE)], $row['industry']),
    'field_tax_cas_career_region' => [['target_id' => $lookup('cas_career_region', $row['region'])]],
    'field_tax_state' => [['target_id' => $state_tid($row['state'])]],
  ];

  $nids = $nodes->getQuery()->accessCheck(FALSE)
    ->condition('type', 'cas_career')
    ->condition('title', $row['title'])
    ->execute();
  if (count($nids) > 1) {
    echo "SKIP {$row['title']}: " . count($nids) . " nodes with this title\n";
    continue;
  }

  if (!$nids) {
    $created++;
    echo "create: {$row['title']}\n";
    if ($apply) {
      $node = Node::create([
        'type' => 'cas_career',
        'title' => $row['title'],
        'langcode' => 'en',
        'uid' => 1,
        'status' => 1,
        'field_domain_access' => [['target_id' => $domain]],
        'field_domain_source' => [['target_id' => $domain]],
      ] + $values);
      $node->setRevisionLogMessage('Imported from Master List of Food Industry Companies');
      $node->save();
    }
    continue;
  }

  $node = $nodes->load(reset($nids));
  $changed = [];
  foreach ($values as $field => $value) {
    $current = array_map(fn($item) => array_intersect_key($item, reset($value) ?: []), $node->get($field)->getValue());
    if ($current != $value) {
      $changed[] = $field;
      $node->set($field, $value);
    }
  }
  if (!$changed) {
    $unchanged++;
    continue;
  }
  $updated++;
  echo "update: {$row['title']} (" . implode(', ', $changed) . ")\n";
  if ($apply) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Updated from Master List of Food Industry Companies');
    $node->save();
  }
}

echo "created $created, updated $updated, unchanged $unchanged\n";

// 3. Every cas_career node (imported or not) belongs to the Food Science group.
$groups = \Drupal::entityTypeManager()->getStorage('group')->loadByProperties([
  'type' => 'basic_group',
  'label' => 'Department of Food Science and Technology',
]);
if (count($groups) !== 1) {
  throw new \RuntimeException('Expected one Department of Food Science and Technology group, found ' . count($groups));
}
$group = reset($groups);
if (!$group->getGroupType()->hasPlugin('group_node:cas_career')) {
  throw new \RuntimeException('cas_career is not installed as basic_group content: import the group config first');
}
$grouped = 0;
$all = $nodes->getQuery()->accessCheck(FALSE)->condition('type', 'cas_career')->execute();
foreach ($nodes->loadMultiple($all) as $node) {
  if ($group->getRelationshipsByEntity($node, 'group_node:cas_career')) {
    continue;
  }
  $grouped++;
  if ($apply) {
    $group->addRelationship($node, 'group_node:cas_career');
  }
}
echo "add to group {$group->id()} ({$group->label()}): $grouped\n";
if (!$apply && $created) {
  echo "  (plus the $created nodes this run would create)\n";
}

// 4. The group's Content Settings offer Career opportunity and its taxonomies.
// An empty list means "offer everything", so it is left alone: adding one
// entry would narrow the group to just that.
$settings = [
  'field_group_content_types' => ['cas_career'],
  'field_group_vocabularies' => ['cas_career_industry', 'cas_career_region', 'state'],
];
$settings_changed = FALSE;
foreach ($settings as $field => $wanted) {
  $current = array_column($group->get($field)->getValue(), 'target_id');
  if (!$current) {
    echo "content settings $field: empty (offers all), left alone\n";
    continue;
  }
  $missing = array_values(array_diff($wanted, $current));
  if ($missing) {
    echo "content settings $field: add " . implode(', ', $missing) . "\n";
    $group->set($field, array_merge($current, $missing));
    $settings_changed = TRUE;
  }
}
if ($settings_changed && $apply) {
  $group->save();
}
echo "cas_career nodes now: " . $db->query("SELECT COUNT(*) FROM {node_field_data} WHERE type = 'cas_career'")->fetchField() . "\n";
