<?php

/**
 * @file
 * Place cas_group_profiles blocks where D7 contexts placed profile views.
 *
 * D7's context module placed fw_profiles / sara_profiles /
 * bee_profiles_membership / profiles_subgroups / people view blocks into the
 * content region by path — a fourth placement system outside the embed
 * audit. Each becomes a "Group people listing" block appended to the page's
 * layout in the D7 context weight order: membership-type sets map directly,
 * FW division displays use the term filter (field_profile_subgroups),
 * graduate-faculty displays use the grad-accept filter. Placements carry
 * 'placement' => 'context' so the embed placement script leaves them alone.
 *
 * Approximations, deliberate: the any-division condition on fw block_9/13 is
 * dropped (types alone within the FW group are equivalent in practice);
 * career-interests-not-empty filters are dropped; the stale
 * applied-economics/people/faculty context (its path no longer exists in D7)
 * and the wildcard employee_directory context are skipped.
 *
 * Usage: drush scr scripts-dev/place_context_profiles_blocks.php
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

// nid => ordered list of settings for cas_group_profiles blocks.
// 'types' => membership type tids ('not_in' complements at runtime),
// 'term' => profile term id, 'grad' => grad-accept values,
// 'all_groups' / 'group' => scope.
$placements = [
  // FW division pages (fw_profiles block_2..block_8).
  44698 => [['term' => 776]],
  44699 => [['term' => 781]],
  44700 => [['term' => 786]],
  44701 => [['term' => 791]],
  44702 => [['term' => 796]],
  44703 => [['term' => 801]],
  // FW courtesy directory (block_13) and faculty directory (block_9).
  114391 => [['exposed' => 'directory_division_courtesy', 'types' => [374, 284]]],
  42111 => [['exposed' => 'directory_division', 'types' => [826, 281, 282, 283, 289, 288, 293, 290]]],
  // SOREC/SARA membership-type directories.
  86346 => [['types' => [4346]]],
  86366 => [['types' => [4336]]],
  86566 => [['types' => [296]]],
  107576 => [['types' => [296]]],
  // Food Science faculty directory: four lists in context weight order.
  94911 => [
    ['types' => [761, 826, 4936, 281, 282, 283, 293, 290, 374]],
    ['types' => [284]],
    ['types' => [441]],
    ['types' => [288]],
  ],
  // Honey Bee Lab directory: faculty, research, grad students.
  80201 => [
    ['types' => [3936, 761, 19251, 826, 4936, 6751, 281, 282, 283, 6761, 6756, 293, 290, 374]],
    ['types' => [861, 285, 286, 287, 289]],
    ['types' => [295]],
  ],
  // Beaver Turf faculty/staff (grad students list).
  78386 => [['types' => [295]]],
  // Graduate faculty pages (grad-accept program flags).
  211791 => [['grad' => ['grad-stu-ent']]],
  44696 => [['grad' => ['grad-stu-fw']]],
  57771 => [
    ['grad' => ['grad-stu-fw']],
    ['grad' => ['grad-stu-css', 'grad-stu-crop', 'grad-stu-soil']],
  ],
  60186 => [['grad' => ['grad-stu-horticulture']]],
  // BEE digital signage: everyone except the excluded types, BEE group.
  166801 => [['not_types' => [4366, 295, 296, 471, 3851, 466, 4346, 4336, 4351, 4341], 'group' => 25345]],
  263201 => [['not_types' => [4366, 295, 296, 471, 3851, 466, 4346, 4336, 4351, 4341], 'group' => 25345]],
  // All CAS People (people block_12): the full directory.
  227111 => [['exposed' => 'directory_names', 'all_groups' => TRUE]],
];

$db = \Drupal::database();
$all_tids = array_map('intval', $db->query("SELECT tid FROM {taxonomy_term_field_data} WHERE vid='membership_types'")->fetchCol());
$storage = \Drupal::entityTypeManager()->getStorage('node');
$uuid = \Drupal::service('uuid');

$placed = $missing_node = 0;
foreach ($placements as $nid => $items) {
  $node = $storage->load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    print "SKIP $nid: no node/layout\n";
    $missing_node++;
    continue;
  }
  $list = $node->get('layout_builder__layout');

  // Reconcile: drop previously context-placed listings and their sections.
  foreach ($list->getSections() as $section) {
    foreach ($section->getComponents() as $c) {
      $cfg = $c->get('configuration');
      if (($cfg['id'] ?? '') === 'cas_group_profiles' && ($cfg['placement'] ?? '') === 'context') {
        $section->removeComponent($c->getUuid());
      }
    }
  }
  for ($si = count($list->getSections()) - 1; $si >= 0; $si--) {
    $section = $list->getSections()[$si];
    if (($section->getLayoutSettings()['label'] ?? '') === 'people (context)' && !$section->getComponents()) {
      $list->removeSection($si);
    }
  }

  // One appended section holds the page's context listings in D7 order.
  $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'people (context)', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
  $list->appendSection($section);
  foreach ($items as $i => $spec) {
    $types = $spec['types'] ?? [];
    if (!empty($spec['not_types'])) {
      $types = array_values(array_diff($all_tids, $spec['not_types']));
    }
    $component = SectionComponent::fromArray([
      'uuid' => $uuid->generate(),
      'region' => 'blb_region_col_1',
      'configuration' => [
        'id' => 'cas_group_profiles',
        'label' => 'People listing',
        'provider' => 'osu_cas_multisite_groups',
        'label_display' => '0',
        'context_mapping' => [],
        'placement' => 'context',
        'display' => 'list',
        'membership_types' => array_map('intval', $types),
        'term' => $spec['term'] ?? NULL,
        'grad_accept' => $spec['grad'] ?? [],
        'all_groups' => !empty($spec['all_groups']),
        'exposed_filter' => $spec['exposed'] ?? 'none',
        'group_override' => $spec['group'] ?? NULL,
      ],
      'additional' => [],
      'weight' => $i,
    ]);
    $section->appendComponent($component);
    $component->setWeight($i);
    $placed++;
  }
  $node->setNewRevision(FALSE);
  $node->setSyncing(TRUE);
  $node->save();
}
printf("Placed: %d  Missing nodes: %d\n", $placed, $missing_node);
