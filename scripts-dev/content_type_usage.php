<?php

/**
 * @file
 * Where each content type is actually used: which groups hold it, and which
 * domains those groups belong to.
 *
 * Nodes reach a domain two ways here, and both matter:
 * - directly, through the node's own field_domain_access
 * - indirectly, through the group that holds it, since groups carry the same
 *   domain fields (basic_group.field_domain_access / _source)
 *
 * Group membership comes from group_relationship_field_data keyed on
 * plugin_id 'group_node:<bundle>', the same relationship
 * CasNodeGroups and CasGroupAddContentBlock use.
 *
 * Writes scripts-dev/ct_usage.json for the artifact.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/content_type_usage.php
 */

$db = \Drupal::database();

$group_label = [];
foreach ($db->query('SELECT id, label FROM {groups_field_data}') as $r) {
  $group_label[(int) $r->id] = $r->label;
}
$domain_label = [];
foreach (\Drupal::entityTypeManager()->getStorage('domain')->loadMultiple() as $d) {
  $domain_label[$d->id()] = $d->label();
}
$type_label = [];
foreach (\Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple() as $t) {
  $type_label[$t->id()] = $t->label();
}

// Group -> its domains.
$group_domains = [];
foreach ($db->query('SELECT entity_id, field_domain_access_target_id AS d FROM {group__field_domain_access}') as $r) {
  $group_domains[(int) $r->entity_id][] = $r->d;
}
$group_all_aff = [];
foreach ($db->query('SELECT entity_id FROM {group__field_domain_all_affiliates} WHERE field_domain_all_affiliates_value = 1') as $r) {
  $group_all_aff[(int) $r->entity_id] = TRUE;
}

// Which bundles each group type permits (installed group_node plugins).
$allowed = [];
foreach (\Drupal::entityTypeManager()->getStorage('group_type')->loadMultiple() as $gt) {
  foreach ($gt->getInstalledPlugins() as $plugin_id => $plugin) {
    if (str_starts_with($plugin_id, 'group_node:')) {
      $allowed[substr($plugin_id, strlen('group_node:'))][] = $gt->id();
    }
  }
}

$report = ['generated' => date('c'), 'types' => []];
foreach ($db->query('SELECT type, COUNT(*) c, SUM(status = 1) pub FROM {node_field_data} WHERE default_langcode = 1 GROUP BY type') as $r) {
  $type = $r->type;

  $groups = $db->query('SELECT gr.gid, COUNT(DISTINCT gr.entity_id) c FROM {group_relationship_field_data} gr
      JOIN {node_field_data} n ON n.nid = gr.entity_id AND n.default_langcode = 1
      WHERE gr.plugin_id = :p GROUP BY gr.gid ORDER BY c DESC', [':p' => 'group_node:' . $type])->fetchAll();

  // Domains reached via the groups holding this type.
  //
  // Counted as distinct nodes, in SQL rather than by summing the per-group
  // figures: a group can sit on several domains and a node in several groups,
  // so adding up group counts credits the same node more than once and the
  // column stops being comparable with the one beside it.
  $via_group = [];
  foreach ($db->query('SELECT gd.field_domain_access_target_id AS id, COUNT(DISTINCT gr.entity_id) c
      FROM {group_relationship_field_data} gr
      JOIN {node_field_data} n ON n.nid = gr.entity_id AND n.default_langcode = 1
      JOIN {group__field_domain_access} gd ON gd.entity_id = gr.gid
      WHERE gr.plugin_id = :p GROUP BY 1 ORDER BY c DESC', [':p' => 'group_node:' . $type]) as $row) {
    $via_group[$row->id] = (int) $row->c;
  }

  // Domains set directly on the nodes.
  $direct = [];
  foreach ($db->query('SELECT d.field_domain_access_target_id AS id, COUNT(DISTINCT d.entity_id) c
      FROM {node__field_domain_access} d
      JOIN {node_field_data} n ON n.nid = d.entity_id AND n.default_langcode = 1
      WHERE n.type = :t GROUP BY 1 ORDER BY c DESC', [':t' => $type]) as $row) {
    $direct[$row->id] = (int) $row->c;
  }

  // Distinct nodes: a node can belong to several groups, so summing the
  // per-group counts overstates coverage and makes "ungrouped" negative.
  $in_groups = (int) $db->query('SELECT COUNT(DISTINCT gr.entity_id) FROM {group_relationship_field_data} gr
      JOIN {node_field_data} n ON n.nid = gr.entity_id AND n.default_langcode = 1
      WHERE gr.plugin_id = :p', [':p' => 'group_node:' . $type])->fetchField();
  $report['types'][] = [
    'id' => $type,
    'label' => $type_label[$type] ?? $type,
    'nodes' => (int) $r->c,
    'published' => (int) $r->pub,
    'in_groups' => $in_groups,
    'ungrouped' => (int) $r->c - $in_groups,
    'group_count' => count($groups),
    'allowed_in' => $allowed[$type] ?? [],
    'top_groups' => array_map(fn($g) => [
      'label' => $group_label[(int) $g->gid] ?? ('group ' . $g->gid),
      'count' => (int) $g->c,
      'domains' => array_map(fn($d) => $domain_label[$d] ?? $d, $group_domains[(int) $g->gid] ?? []),
      'all_affiliates' => isset($group_all_aff[(int) $g->gid]),
    ], array_slice($groups, 0, 8)),
    'domains_via_groups' => array_map(fn($d, $c) => ['label' => $domain_label[$d] ?? $d, 'count' => $c],
      array_keys($via_group), array_values($via_group)),
    'domains_direct' => array_map(fn($d, $c) => ['label' => $domain_label[$d] ?? $d, 'count' => $c],
      array_keys($direct), array_values($direct)),
  ];
}
usort($report['types'], fn($a, $b) => $b['nodes'] <=> $a['nodes']);

// ---------------------------------------------------------------------------
// Pivot 2: each domain, the groups assigned to it and the content mix those
// groups hold. A domain reaches content through its groups, so a group holding
// no nodes is a section of the site with nothing behind it.
//
// "Without content" means no group_node:* relationships. It says nothing about
// group_membership or group_content_menu, and every such group here has both --
// they are staged containers, not debris. Counts are also per domain, so a
// group assigned to several domains is counted once in each.
// ---------------------------------------------------------------------------
$domains = [];
foreach (\Drupal::entityTypeManager()->getStorage('domain')->loadMultiple() as $d) {
  $domains[$d->id()] = [
    'id' => $d->id(),
    'label' => $d->label(),
    'hostname' => preg_replace('~^ddev\.~', '', $d->getHostname()),
    'is_default' => $d->isDefault(),
    'nodes' => 0,
    'groups' => [],
    'types' => [],
  ];
}
foreach ($db->query('SELECT field_domain_access_target_id AS d, COUNT(DISTINCT entity_id) c
  FROM {node__field_domain_access} GROUP BY 1') as $r) {
  if (isset($domains[$r->d])) {
    $domains[$r->d]['nodes'] = (int) $r->c;
  }
}
// Groups per domain, with how much each holds.
$group_node_count = [];
foreach ($db->query("SELECT gid, COUNT(DISTINCT entity_id) c FROM {group_relationship_field_data}
  WHERE plugin_id LIKE 'group\_node:%' GROUP BY gid") as $r) {
  $group_node_count[(int) $r->gid] = (int) $r->c;
}
foreach ($db->query('SELECT entity_id AS gid, field_domain_access_target_id AS d FROM {group__field_domain_access}') as $r) {
  if (!isset($domains[$r->d])) {
    continue;
  }
  $gid = (int) $r->gid;
  $domains[$r->d]['groups'][] = [
    'label' => $group_label[$gid] ?? ('group ' . $gid),
    'nodes' => $group_node_count[$gid] ?? 0,
  ];
}
// Content-type mix per domain, straight from the nodes' own domain field.
foreach ($db->query('SELECT da.field_domain_access_target_id AS d, n.type, COUNT(DISTINCT n.nid) c
  FROM {node__field_domain_access} da
  JOIN {node_field_data} n ON n.nid = da.entity_id AND n.default_langcode = 1
  GROUP BY 1, 2') as $r) {
  if (isset($domains[$r->d])) {
    $domains[$r->d]['types'][$r->type] = (int) $r->c;
  }
}
foreach ($domains as &$dom) {
  usort($dom['groups'], fn($a, $b) => $b['nodes'] <=> $a['nodes']);
  arsort($dom['types']);
  $dom['empty_groups'] = count(array_filter($dom['groups'], fn($g) => $g['nodes'] === 0));
}
unset($dom);
uasort($domains, fn($a, $b) => $b['nodes'] <=> $a['nodes']);
$report['domains'] = array_values($domains);

// Groups holding no nodes at all, whichever domain they sit on.
$empty = [];
foreach ($db->query('SELECT id FROM {groups_field_data} WHERE default_langcode = 1') as $r) {
  $gid = (int) $r->id;
  if (($group_node_count[$gid] ?? 0) > 0) {
    continue;
  }
  $empty[] = [
    'label' => $group_label[$gid] ?? ('group ' . $gid),
    'domains' => array_map(fn($x) => $domain_label[$x] ?? $x, $group_domains[$gid] ?? []),
  ];
}
usort($empty, fn($a, $b) => strcasecmp($a['label'], $b['label']));
$report['empty_groups'] = $empty;
$report['group_totals'] = [
  'all' => (int) $db->query('SELECT COUNT(*) FROM {groups_field_data} WHERE default_langcode = 1')->fetchField(),
  'empty' => count($empty),
];

// ---------------------------------------------------------------------------
// Pivot 3: the parent-group tier.
//
// D7's 12 'parent_unit' nodes were a hierarchy tier above the groups that
// never held content, so they are not migrated. Instead each group's
// field_group_parent names the parent unit's DEFAULT content group -- the
// department group the D7 header link effectively resolved to -- and the two
// parent units that had no default group (Agricultural Experiment Station,
// Corvallis Farm Unit) were replaced by real groups with a landing page
// (cas_parent_unit_default_group). Parent groups here are therefore ordinary
// content groups that other groups point at.
// ---------------------------------------------------------------------------
$children = [];
foreach ($db->query('SELECT entity_id AS gid, field_group_parent_target_id AS parent
  FROM {group__field_group_parent}') as $r) {
  $children[(int) $r->parent][] = (int) $r->gid;
}

$describe = fn(int $gid) => [
  'label' => $group_label[$gid] ?? ('group ' . $gid),
  'nodes' => $group_node_count[$gid] ?? 0,
  'domains' => array_map(fn($x) => $domain_label[$x] ?? $x, $group_domains[$gid] ?? []),
];

// A parent group is any group another group points at.
$parent_gids = array_keys($children);

$parents = [];
foreach ($parent_gids as $gid) {
  $kids = array_map($describe, $children[$gid] ?? []);
  usort($kids, fn($a, $b) => $b['nodes'] <=> $a['nodes'] ?: strcasecmp($a['label'], $b['label']));
  $parents[] = $describe($gid) + [
    'children' => $kids,
    'child_nodes' => array_sum(array_column($kids, 'nodes')),
  ];
}
usort($parents, fn($a, $b) => $b['child_nodes'] <=> $a['child_nodes'] ?: strcasecmp($a['label'], $b['label']));
$report['parent_units'] = $parents;

// Groups that answer to no parent unit, so the tier's coverage is legible.
$has_parent = array_flip(array_merge(...array_values($children) ?: [[]]));
$unparented = [];
foreach (array_keys($group_label) as $gid) {
  if (!isset($has_parent[$gid]) && !in_array($gid, $parent_gids, TRUE)) {
    $unparented[] = $describe($gid);
  }
}
usort($unparented, fn($a, $b) => $b['nodes'] <=> $a['nodes'] ?: strcasecmp($a['label'], $b['label']));
$report['unparented_groups'] = $unparented;

file_put_contents(DRUPAL_ROOT . '/../scripts-dev/ct_usage.json', json_encode($report, JSON_PRETTY_PRINT));
printf("types: %d | nodes: %d | grouped: %d | domains: %d | empty groups: %d\n",
  count($report['types']),
  array_sum(array_column($report['types'], 'nodes')),
  array_sum(array_column($report['types'], 'in_groups')),
  count($report['domains']), $report['group_totals']['empty']);
