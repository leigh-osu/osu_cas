<?php

/**
 * @file
 * MMI: merge migrated profiles into the agsci profile the person already has.
 *
 * The MMI profile migration creates a fresh osu_profile node per D7 MMI
 * user. People who already have an agsci profile end up with two. For each
 * such pair the agsci node is the keeper:
 *  - fields the keeper has empty are filled from the MMI node (the keeper's
 *    own values are never overwritten);
 *  - the keeper is assigned to the MMI domain as well;
 *  - the MMI node's group relationships move to the keeper (in place, so the
 *    placement map and membership type survive);
 *  - entity references and redirects pointing at the MMI node are repointed;
 *  - the MMI node's /people/* aliases become redirects to the keeper (an
 *    alias would hijack the keeper's canonical URL on agsci);
 *  - the profile map rows are marked ignored with no destination, so a
 *    rollback can never delete the keeper and an import will not recreate
 *    the duplicate;
 *  - the MMI node is deleted.
 *
 * Match order: same field_profile_user, same ONID, same email, same title.
 * Anything with zero or several candidates is reported and left alone.
 *
 * Usage: drush scr scripts-dev/mmi_merge_duplicate_profiles.php [apply]
 */

use Drupal\redirect\Entity\Redirect;

$apply = in_array('apply', $extra ?? [], TRUE);
$db = \Drupal::database();
$etm = \Drupal::entityTypeManager();
$nodes = $etm->getStorage('node');
$mmi_domain = 'mmi_oregonstate_edu';
$skip_fields = ['field_domain_access', 'field_domain_source', 'field_domain_all_affiliates', 'field_group_private'];
$profile_maps = ['migrate_map_mmi_profiles', 'migrate_map_mmi_profile_faculty', 'migrate_map_mmi_profile_person', 'migrate_map_mmi_profile_student', 'migrate_map_mmi_profile_employee'];

$mmi_nids = $db->query("SELECT destid1 FROM {migrate_map_mmi_profiles} WHERE destid1 IS NOT NULL")->fetchCol();
if (!$mmi_nids) {
  print "No mapped MMI profiles.\n";
  return;
}

// Every configurable entity reference field that targets nodes.
$ref_tables = [];
$efm = \Drupal::service('entity_field.manager');
foreach ($efm->getFieldMapByFieldType('entity_reference') as $entity_type => $fields) {
  $storages = $efm->getFieldStorageDefinitions($entity_type);
  foreach (array_keys($fields) as $field) {
    if (!isset($storages[$field]) || $storages[$field]->isBaseField() || $storages[$field]->getSetting('target_type') !== 'node') {
      continue;
    }
    foreach ([$entity_type . '__' . $field, $entity_type . '_revision__' . $field] as $table) {
      if ($db->schema()->tableExists($table)) {
        $ref_tables[$table] = $field . '_target_id';
      }
    }
  }
}

$candidates = function (string $sql, array $args) use ($db, $mmi_nids): array {
  return $db->query("SELECT n.nid FROM {node_field_data} n $sql AND n.type = 'osu_profile' AND n.nid NOT IN (:mmi[])", $args + [':mmi[]' => $mmi_nids])->fetchCol();
};

$merged = 0;
$unmatched = [];
foreach ($nodes->loadMultiple($mmi_nids) as $mmi) {
  if ($mmi->bundle() !== 'osu_profile') {
    continue;
  }
  $uid = (int) $mmi->get('field_profile_user')->target_id;
  $onid = strtolower(trim((string) $mmi->get('field_profile_dm_onid')->value));
  $emails = array_unique(array_filter([
    strtolower(trim((string) $mmi->get('field_profile_email')->value)),
    $uid ? strtolower((string) $mmi->get('field_profile_user')->entity?->getEmail()) : '',
    $onid ? $onid . '@oregonstate.edu' : '',
  ]));

  $found = [];
  $how = '';
  if ($uid) {
    $found = $candidates("JOIN {node__field_profile_user} u ON u.entity_id = n.nid WHERE u.field_profile_user_target_id = :v", [':v' => $uid]);
    $how = 'account';
  }
  if (!$found && $onid) {
    $found = $candidates("JOIN {node__field_profile_dm_onid} o ON o.entity_id = n.nid WHERE LOWER(o.field_profile_dm_onid_value) = :v", [':v' => $onid]);
    $how = 'ONID';
  }
  if (!$found && $emails) {
    $found = $candidates("JOIN {node__field_profile_email} e ON e.entity_id = n.nid WHERE LOWER(e.field_profile_email_value) IN (:v[])", [':v[]' => $emails]);
    $how = 'email';
  }
  if (!$found) {
    $found = $candidates("WHERE n.title = :v", [':v' => $mmi->label()]);
    $how = 'name';
  }
  if (!$found) {
    continue;
  }
  if (count($found) > 1) {
    $unmatched[] = "{$mmi->id()} {$mmi->label()}: " . count($found) . " candidates by $how (" . implode(',', $found) . ')';
    continue;
  }
  $keeper = $nodes->load(reset($found));
  $from = (int) $mmi->id();
  $to = (int) $keeper->id();

  // Fields: fill the keeper's gaps only.
  $filled = [];
  $kept = [];
  foreach ($mmi->getFieldDefinitions() as $name => $definition) {
    if (!str_starts_with($name, 'field_') || in_array($name, $skip_fields, TRUE) || !$keeper->hasField($name) || $mmi->get($name)->isEmpty()) {
      continue;
    }
    if ($keeper->get($name)->isEmpty()) {
      $keeper->set($name, $mmi->get($name)->getValue());
      $filled[] = substr($name, 6);
    }
    elseif ($keeper->get($name)->getValue() != $mmi->get($name)->getValue()) {
      $kept[] = substr($name, 6);
    }
  }
  $domains = array_column($keeper->get('field_domain_access')->getValue(), 'target_id');
  $add_domain = !in_array($mmi_domain, $domains, TRUE);
  if ($add_domain) {
    $keeper->get('field_domain_access')->appendItem(['target_id' => $mmi_domain]);
  }

  $rels = $etm->getStorage('group_content')->loadByProperties(['plugin_id' => 'group_node:osu_profile', 'entity_id' => $from]);
  $keeper_gids = $db->query("SELECT gid FROM {group_relationship_field_data} WHERE plugin_id = 'group_node:osu_profile' AND entity_id = :n", [':n' => $to])->fetchCol();
  $aliases = $db->query("SELECT id, alias FROM {path_alias} WHERE path = :p", [':p' => '/node/' . $from])->fetchAllKeyed();
  $redirect_ids = $db->query("SELECT rid FROM {redirect} WHERE redirect_redirect__uri IN (:u[])", [':u[]' => ['internal:/node/' . $from, 'entity:node/' . $from]])->fetchCol();
  $ref_count = 0;
  foreach ($ref_tables as $table => $column) {
    $ref_count += (int) $db->query("SELECT COUNT(*) FROM {" . $table . "} WHERE $column = :n", [':n' => $from])->fetchField();
  }

  $merged++;
  print "$from -> $to {$mmi->label()} [$how] groups " . count($rels) . ', aliases ' . count($aliases) . ', redirects ' . count($redirect_ids) . ", refs $ref_count" . ($add_domain ? ', +mmi domain' : '') . "\n";
  print '    filled: ' . ($filled ? implode(', ', $filled) : '-') . "\n";
  if ($kept) {
    print '    kept agsci value (MMI differs): ' . implode(', ', $kept) . "\n";
  }
  if (!$apply) {
    continue;
  }

  $keeper->setNewRevision(FALSE);
  $keeper->setSyncing(TRUE);
  $keeper->save();

  foreach ($rels as $rel) {
    if (in_array($rel->getGroupId(), $keeper_gids)) {
      $rel->delete();
      continue;
    }
    $rel->set('entity_id', $to);
    $rel->set('label', $keeper->label());
    $rel->save();
  }

  foreach ($ref_tables as $table => $column) {
    $db->update($table)->fields([$column => $to])->condition($column, $from)->execute();
  }

  foreach ($redirect_ids as $rid) {
    if ($redirect = Redirect::load($rid)) {
      $redirect->setRedirect('node/' . $to);
      $redirect->save();
    }
  }

  // Old MMI URLs keep working as redirects; the pathauto "-0" duplicate of
  // the keeper's own alias is simply dropped with the node.
  foreach ($aliases as $alias) {
    if (preg_match('#^/directory/people/.*-\d+$#', $alias)) {
      continue;
    }
    $source = ltrim($alias, '/');
    $exists = $etm->getStorage('redirect')->loadByProperties(['redirect_source__path' => $source]);
    if (!$exists) {
      Redirect::create([
        'redirect_source' => ['path' => $source, 'query' => []],
        'redirect_redirect' => ['uri' => 'internal:/node/' . $to],
        'status_code' => 301,
        'language' => 'und',
      ])->save();
    }
  }

  foreach ($profile_maps as $map) {
    if ($db->schema()->tableExists($map)) {
      $db->update($map)->fields(['destid1' => NULL, 'source_row_status' => 2])->condition('destid1', $from)->execute();
    }
  }

  $mmi->delete();
}

foreach ($unmatched as $line) {
  print "AMBIGUOUS $line\n";
}
print ($apply ? 'Merged' : 'Would merge') . " $merged profile(s); " . count($unmatched) . " ambiguous\n";
