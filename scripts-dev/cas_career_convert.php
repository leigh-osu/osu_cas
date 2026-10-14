<?php

/**
 * @file
 * Remakes the agsci_company node type as cas_career ("Career opportunity").
 *
 * Builds the cas_career type, its field_cas_career_* fields (field_tax_* for
 * the taxonomy references) and the cas_career_{industry,region} vocabularies
 * from the agsci_company originals, copies terms and nodes across, repoints
 * the agricultural_companies view, then deletes the old type and
 * vocabularies. The generic state vocabulary is kept as it is.
 * Old node aliases are kept as redirects to the new nodes.
 *
 * Content is converted in place, so this runs per environment:
 *   drush --uri=https://osu-cas.ddev.site scr scripts-dev/cas_career_convert.php
 * Each step skips work already done, so a re-run after a failure resumes.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\redirect\Entity\Redirect;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\views\Entity\View;

const OLD_TYPE = 'agsci_company';
const NEW_TYPE = 'cas_career';

$vocab_map = [
  'industry' => 'cas_career_industry',
  'state' => 'state',
  'regions_u_s' => 'cas_career_region',
];
// Vocabularies copied under a new name; the rest are referenced as they are.
$renamed_vocabs = array_filter($vocab_map, fn($new, $old) => $new !== $old, ARRAY_FILTER_USE_BOTH);
$vocab_labels = [
  'cas_career_industry' => 'CAS Career Industry',
  'cas_career_region' => 'CAS Career Region',
];
$field_map = [
  'body' => 'field_cas_career_body',
  'field_about_us' => 'field_cas_career_about_us',
  'field_careers' => 'field_cas_career_careers',
  'field_city' => 'field_cas_career_city',
  'field_industry' => 'field_tax_cas_career_industry',
  'field_state' => 'field_tax_state',
  'field_region' => 'field_tax_cas_career_region',
];
$domain_fields = [
  'field_domain_access',
  'field_domain_all_affiliates',
  'field_domain_source',
];

$etm = \Drupal::entityTypeManager();
$term_storage = $etm->getStorage('taxonomy_term');
$say = fn(string $msg) => print($msg . PHP_EOL);

// New config takes its UUID from the sync directory when that holds an
// export, so a config import after this script updates rather than
// deletes-and-recreates (which would drop field data).
$sync = \Drupal::service('config.storage.sync');
$save_config = function ($entity) use ($sync) {
  $exported = $sync->read($entity->getConfigDependencyName());
  if (!empty($exported['uuid'])) {
    $entity->set('uuid', $exported['uuid']);
  }
  $entity->save();
};

// Renames old field names inside a string, including column and table
// forms such as node__field_city and field_industry_target_id.
$rename = function (string $s) use ($field_map): string {
  foreach ($field_map as $old => $new) {
    if ($old === 'body') {
      $s = preg_replace('/^body$|(?<=node__)body(?![a-z])|^body(?=_(value|summary|format)$)/', $new, $s);
      continue;
    }
    $s = preg_replace('/(?<![a-z0-9])' . $old . '(?![a-z])/', $new, $s);
  }
  return $s;
};

// 1. Vocabularies and terms.
$tid_map = [];
foreach ($renamed_vocabs as $old_vid => $new_vid) {
  $old_vocab = Vocabulary::load($old_vid);
  if (!Vocabulary::load($new_vid)) {
    if (!$old_vocab) {
      throw new \RuntimeException("Neither $old_vid nor $new_vid exists.");
    }
    $vocab = $old_vocab->createDuplicate();
    $vocab->set('vid', $new_vid);
    $vocab->set('name', $vocab_labels[$new_vid]);
    $save_config($vocab);
    $say("Created vocabulary $new_vid");
  }
  $old_settings = ContentLanguageSettings::load("taxonomy_term.$old_vid");
  if ($old_settings && !ContentLanguageSettings::load("taxonomy_term.$new_vid")) {
    $settings = $old_settings->createDuplicate();
    $settings->set('id', "taxonomy_term.$new_vid");
    $settings->set('target_bundle', $new_vid);
    $save_config($settings);
  }
  if (!$old_vocab) {
    continue;
  }

  // Match on name so a re-run reuses terms already copied.
  $existing = [];
  foreach ($term_storage->loadByProperties(['vid' => $new_vid]) as $term) {
    $existing[$term->getName()] = $term;
  }
  $old_terms = $term_storage->loadByProperties(['vid' => $old_vid]);
  foreach ($old_terms as $old_term) {
    $new_term = $existing[$old_term->getName()] ?? NULL;
    if (!$new_term) {
      // Pathauto stays off until the old terms (and their aliases) are gone,
      // otherwise the new aliases collide and get a -0 suffix.
      $new_term = Term::create([
        'vid' => $new_vid,
        'name' => $old_term->getName(),
        'description' => $old_term->get('description')->getValue(),
        'weight' => $old_term->getWeight(),
        'status' => $old_term->isPublished(),
        'langcode' => $old_term->language()->getId(),
        'path' => ['pathauto' => 0],
      ]);
      $new_term->save();
    }
    $tid_map[$old_term->id()] = $new_term->id();
  }
  foreach ($old_terms as $old_term) {
    $parents = [];
    foreach ($old_term->get('parent') as $item) {
      $parents[] = $item->target_id ? $tid_map[$item->target_id] : 0;
    }
    $new_term = Term::load($tid_map[$old_term->id()]);
    $current = array_column($new_term->get('parent')->getValue(), 'target_id');
    if ($current != $parents) {
      $new_term->set('parent', $parents);
      $new_term->save();
    }
  }
  $say(sprintf('%s: %d terms mapped', $new_vid, count($old_terms)));
}

// 2. Field storages.
foreach ($field_map as $old_name => $new_name) {
  if (FieldStorageConfig::loadByName('node', $new_name)) {
    continue;
  }
  $values = FieldStorageConfig::loadByName('node', $old_name)->toArray();
  unset($values['uuid'], $values['_core'], $values['id'], $values['dependencies']);
  $values['field_name'] = $new_name;
  $save_config(FieldStorageConfig::create($values));
  $say("Created storage $new_name");
}

// 3. Node type.
$old_type = NodeType::load(OLD_TYPE);
if (!NodeType::load(NEW_TYPE)) {
  $type = $old_type->createDuplicate();
  $type->set('type', NEW_TYPE);
  $type->set('name', 'Career opportunity');
  $type->setDisplaySubmitted(FALSE);
  $save_config($type);
  $say('Created node type ' . NEW_TYPE);
}
$old_settings = ContentLanguageSettings::load('node.' . OLD_TYPE);
if ($old_settings && !ContentLanguageSettings::load('node.' . NEW_TYPE)) {
  $settings = $old_settings->createDuplicate();
  $settings->set('id', 'node.' . NEW_TYPE);
  $settings->set('target_bundle', NEW_TYPE);
  $save_config($settings);
}

if ($old_type) {
  // 4. Fields on the bundle.
  foreach (array_merge(array_keys($field_map), $domain_fields) as $old_name) {
    $new_name = $field_map[$old_name] ?? $old_name;
    if ($field = FieldConfig::loadByName('node', NEW_TYPE, $new_name)) {
      // domain_access adds its fields when the type is inserted, under fresh
      // UUIDs; swap those for the exported ones while the bundle is empty.
      $exported = $sync->read($field->getConfigDependencyName());
      if (empty($exported['uuid']) || $exported['uuid'] === $field->uuid() || !in_array($old_name, $domain_fields, TRUE)) {
        continue;
      }
      $field->delete();
    }
    $values = FieldConfig::loadByName('node', OLD_TYPE, $old_name)->toArray();
    unset($values['uuid'], $values['_core'], $values['id'], $values['dependencies']);
    $values['bundle'] = NEW_TYPE;
    $values['field_name'] = $new_name;
    if (!empty($values['settings']['handler_settings']['target_bundles'])) {
      $bundles = [];
      foreach ($values['settings']['handler_settings']['target_bundles'] as $vid) {
        $bundles[$vocab_map[$vid]] = $vocab_map[$vid];
      }
      $values['settings']['handler_settings']['target_bundles'] = $bundles;
    }
    $save_config(FieldConfig::create($values));
    $say("Created field $new_name");
  }

  // 5. Base field overrides.
  foreach (['title', 'promote'] as $base) {
    if (BaseFieldOverride::load('node.' . NEW_TYPE . ".$base")) {
      continue;
    }
    $values = BaseFieldOverride::load('node.' . OLD_TYPE . ".$base")->toArray();
    unset($values['uuid'], $values['_core'], $values['dependencies']);
    $values['id'] = 'node.' . NEW_TYPE . ".$base";
    $values['bundle'] = NEW_TYPE;
    if ($base === 'title') {
      $values['label'] = 'Company/Organization name';
    }
    $save_config(BaseFieldOverride::create($values));
  }

  // 6. Form and view displays, with component keys renamed.
  $displays = [
    [EntityFormDisplay::class, 'default'],
    [EntityViewDisplay::class, 'default'],
    [EntityViewDisplay::class, 'teaser'],
  ];
  foreach ($displays as [$class, $mode]) {
    if ($class::load('node.' . NEW_TYPE . ".$mode")) {
      continue;
    }
    $values = $class::load('node.' . OLD_TYPE . ".$mode")->toArray();
    unset($values['uuid'], $values['_core'], $values['dependencies']);
    $values['id'] = 'node.' . NEW_TYPE . ".$mode";
    $values['bundle'] = NEW_TYPE;
    foreach (['content', 'hidden'] as $key) {
      $renamed = [];
      foreach ($values[$key] as $name => $component) {
        $renamed[$field_map[$name] ?? $name] = $component;
      }
      $values[$key] = $renamed;
    }
    $save_config($class::create($values));
  }
  $say('Displays copied');

  // 7. Role permissions.
  foreach (Role::loadMultiple() as $role) {
    $changed = FALSE;
    foreach ($role->getPermissions() as $permission) {
      $new_permission = preg_replace('/ ' . OLD_TYPE . ' /', ' ' . NEW_TYPE . ' ', $permission);
      if ($new_permission !== $permission && !$role->hasPermission($new_permission)) {
        $role->grantPermission($new_permission);
        $changed = TRUE;
      }
    }
    if ($changed) {
      $role->save();
      $say('Granted ' . NEW_TYPE . ' permissions to ' . $role->id());
    }
  }

  // 8. Nodes.
  $alias_manager = \Drupal::service('path_alias.manager');
  $nid_map = \Drupal::state()->get('cas_career_convert.nid_map', []);
  $old_nids = $etm->getStorage('node')->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', OLD_TYPE)
    ->execute();
  foreach (Node::loadMultiple($old_nids) as $old) {
    if (isset($nid_map[$old->id()]) && Node::load($nid_map[$old->id()])) {
      continue;
    }
    if (count($old->getTranslationLanguages()) > 1) {
      throw new \RuntimeException('Node ' . $old->id() . ' has translations; extend the script.');
    }
    $values = [
      'type' => NEW_TYPE,
      'title' => $old->getTitle(),
      'uid' => $old->getOwnerId(),
      'status' => $old->isPublished(),
      'created' => $old->getCreatedTime(),
      'changed' => $old->getChangedTime(),
      'promote' => $old->isPromoted(),
      'sticky' => $old->isSticky(),
      'langcode' => $old->language()->getId(),
      'revision_log' => 'Converted from ' . OLD_TYPE . ' node ' . $old->id(),
    ];
    foreach ($domain_fields as $name) {
      $values[$name] = $old->get($name)->getValue();
    }
    foreach ($field_map as $old_name => $new_name) {
      $items = $old->get($old_name)->getValue();
      if (in_array($old_name, ['field_industry', 'field_state', 'field_region'], TRUE)) {
        foreach ($items as &$item) {
          $item = ['target_id' => $tid_map[$item['target_id']] ?? $item['target_id']];
        }
        unset($item);
      }
      $values[$new_name] = $items;
    }
    $new = Node::create($values);
    $new->setChangedTime($old->getChangedTime());
    $new->save();
    $nid_map[$old->id()] = $new->id();
    \Drupal::state()->set('cas_career_convert.nid_map', $nid_map);

    $old_alias = $alias_manager->getAliasByPath('/node/' . $old->id(), $old->language()->getId());
    if ($old_alias !== '/node/' . $old->id()) {
      $redirect = Redirect::create();
      $redirect->setSource(ltrim($old_alias, '/'));
      $redirect->setRedirect('node/' . $new->id());
      $redirect->setLanguage('und');
      $redirect->setStatusCode(301);
      $redirect->save();
    }
    $say(sprintf('Node %d -> %d (%s), redirect from %s', $old->id(), $new->id(), $new->getTitle(), $old_alias));
  }
}

// 9. View: repoint fields, filters and vocabularies.
$view = View::load('agricultural_companies');
$walk = function ($value) use (&$walk, $rename) {
  if (is_array($value)) {
    $out = [];
    foreach ($value as $k => $v) {
      $k = is_string($k) ? $rename($k) : $k;
      $out[$k] = $walk($v);
    }
    return $out;
  }
  if (is_string($value)) {
    return $rename($value === OLD_TYPE ? NEW_TYPE : $value);
  }
  return $value;
};
$displays = $view->get('display');
foreach ($displays as &$display) {
  if (isset($display['display_options']['filters'])) {
    foreach ($display['display_options']['filters'] as &$filter) {
      if (($filter['plugin_id'] ?? '') === 'bundle') {
        $filter['value'] = [NEW_TYPE => NEW_TYPE];
      }
      if (isset($filter['vid'], $vocab_map[$filter['vid']])) {
        $filter['vid'] = $vocab_map[$filter['vid']];
      }
    }
    unset($filter);
  }
  $display = $walk($display);
}
unset($display);
$view->set('display', $displays);
$view->save();
$say('View agricultural_companies repointed');

// The listing moves from /agricultural-companies to /cas-careers when the
// exported view is imported; keep the old path working.
$redirect_storage = $etm->getStorage('redirect');
if (!$redirect_storage->loadByProperties(['redirect_source__path' => 'agricultural-companies'])) {
  $redirect = Redirect::create();
  $redirect->setSource('agricultural-companies');
  $redirect->setRedirect('cas-careers');
  $redirect->setLanguage('und');
  $redirect->setStatusCode(301);
  $redirect->save();
  $say('Redirect agricultural-companies -> cas-careers');
}

// 10. Delete the old nodes, type and vocabularies.
$old_nids = $etm->getStorage('node')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', OLD_TYPE)
  ->execute();
$nid_map = \Drupal::state()->get('cas_career_convert.nid_map', []);
if (array_diff($old_nids, array_keys($nid_map))) {
  throw new \RuntimeException('Unconverted ' . OLD_TYPE . ' nodes remain; not deleting.');
}
$etm->getStorage('node')->delete(Node::loadMultiple($old_nids));
if ($old_type = NodeType::load(OLD_TYPE)) {
  $old_type->delete();
  $say('Deleted node type ' . OLD_TYPE);
}
foreach (array_keys($renamed_vocabs) as $old_vid) {
  if ($vocab = Vocabulary::load($old_vid)) {
    $vocab->delete();
    $say("Deleted vocabulary $old_vid");
  }
}

// 11. Term aliases, now that the old ones are gone.
foreach ($renamed_vocabs as $new_vid) {
  foreach ($term_storage->loadByProperties(['vid' => $new_vid]) as $term) {
    $term->path->pathauto = 1;
    $term->save();
  }
}
$say('Term aliases generated');
field_purge_batch(500);
$say('Done');
