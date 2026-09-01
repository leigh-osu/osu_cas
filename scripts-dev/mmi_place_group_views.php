<?php

/**
 * @file
 * Fill the "D7 view: …" placeholder sections in MMI layouts.
 *
 * MmiParagraphsLayout turned each D7 viewfield paragraph into an empty
 * section labeled "D7 view: <vname>|<display>". This places the D10
 * equivalent block into each one, following the agsci conventions:
 * - mmi_people          -> cas_group_profiles (the osu_cas_multisite block
 *                          wrapping profiles_group_membership; 215 agsci
 *                          placements set the convention)
 * - mmi_news blocks 1/2 -> osu_stories__groups landing block
 * - mmi_news block 3    -> news_items_by_group list (paged archive)
 * - lab_biblio          -> publications_by_group list
 * - image_gallery       -> image_galleries group_images
 * - research_projects   -> research_projects_by_group (new view: the agsci
 *                          projects shape against MMI's research_project
 *                          type; full list / active / completed)
 * Every block resolves its group from the page (cas_current_group), so no
 * per-node arguments are baked in.
 *
 * The expeditions placeholder (Natural History Expeditions, 400169) gets
 * editorial closure instead of a view: the expedition content type was
 * dropped (its one node held a 2020 itinerary that D7's editors themselves
 * superseded — D7 redirects the expedition slug to the current
 * /baja-expedition page family). The section becomes a pointer to that
 * page, and the old slug gets the same redirect D7 gave it.
 *
 * A section is filled only while it still carries the "D7 view:" label and
 * has no components; filling renames it, so re-runs converge. Any other
 * unmapped views are left as placeholders and reported.
 *
 * Run via mmi_migrate.sh section 11.
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\redirect\Entity\Redirect;

$views_block = fn(string $block_id) => [
  'id' => 'views_block:' . $block_id,
  'label' => '',
  'label_display' => '0',
  'provider' => 'views',
  'context_mapping' => [],
  'views_label' => '',
  'items_per_page' => 'none',
];

$map = [
  'mmi_people|block_1' => ['label' => 'People', 'config' => ['id' => 'cas_group_profiles', 'label' => '', 'label_display' => '0', 'provider' => 'osu_cas_multisite_groups', 'context_mapping' => []]],
  'mmi_people|block_2' => ['label' => 'People', 'config' => ['id' => 'cas_group_profiles', 'label' => '', 'label_display' => '0', 'provider' => 'osu_cas_multisite_groups', 'context_mapping' => []]],
  'mmi_news|block_1' => ['label' => 'Stories', 'config' => $views_block('osu_stories__groups-group_landing_page_stories_block')],
  'mmi_news|block_2' => ['label' => 'Stories', 'config' => $views_block('osu_stories__groups-group_landing_page_stories_block')],
  'mmi_news|block_3' => ['label' => 'Story archive', 'config' => $views_block('news_items_by_group-list')],
  'lab_biblio|block' => ['label' => 'Publications', 'config' => $views_block('publications_by_group-list')],
  'image_gallery|block_4' => ['label' => 'Photo albums', 'config' => $views_block('image_galleries-group_images')],
  'research_projects|default' => ['label' => 'Research projects', 'config' => $views_block('research_projects_by_group-list')],
  'research_projects|block_4' => ['label' => 'Active research projects', 'config' => $views_block('research_projects_by_group-active')],
  'research_projects|block_2' => ['label' => 'Completed research projects', 'config' => $views_block('research_projects_by_group-completed')],
];

$db = \Drupal::database();
$nids = $db->query("SELECT DISTINCT entity_id FROM {node__layout_builder__layout} WHERE entity_id >= 400000 AND layout_builder__layout_section LIKE :l", [':l' => '%D7 view:%'])->fetchCol();

$placed = 0;
$left = [];
foreach ($nids as $nid) {
  $node = Node::load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    continue;
  }
  $layout = $node->get('layout_builder__layout');
  $changed = FALSE;
  foreach ($layout->getSections() as $section) {
    $label = $section->getLayoutSettings()['label'] ?? '';
    if (!str_starts_with($label, 'D7 view:')) {
      continue;
    }
    $key = trim(substr($label, 8));
    if ($key === 'expeditions|default') {
      // Handled by the editorial closure below.
      continue;
    }
    if (!isset($map[$key])) {
      $left[] = "$nid ({$node->label()}): $key";
      continue;
    }
    if (count($section->getComponents()) > 0) {
      continue;
    }
    $component = new SectionComponent(\Drupal::service('uuid')->generate(), 'blb_region_col_1', $map[$key]['config']);
    $section->appendComponent($component);
    $settings = $section->getLayoutSettings();
    $settings['label'] = $map[$key]['label'];
    $section->setLayoutSettings($settings);
    $placed++;
    $changed = TRUE;
  }
  if ($changed) {
    $node->save();
    print "  filled: $nid ({$node->label()})\n";
  }
}
// ---- Expeditions: editorial closure ---------------------------------------
$exp_node = Node::load(400169);
if ($exp_node && $exp_node->hasField('layout_builder__layout')) {
  $layout = $exp_node->get('layout_builder__layout');
  foreach ($layout->getSections() as $section) {
    if (($section->getLayoutSettings()['label'] ?? '') !== 'D7 view: expeditions|default' || count($section->getComponents()) > 0) {
      continue;
    }
    $block = BlockContent::create([
      'type' => 'paragraph_block',
      'reusable' => 0,
      'info' => 'Natural History Expeditions pointer',
      'body' => [
        'value' => '<p>The Marine Mammal Institute&rsquo;s natural history expedition program continues with the <a href="/baja-expedition">Baja Gray Whale Expedition</a>.</p>',
        'format' => 'full_html',
      ],
    ]);
    $block->save();
    $section->appendComponent(new SectionComponent(\Drupal::service('uuid')->generate(), 'blb_region_col_1', [
      'id' => 'inline_block:paragraph_block',
      'label' => 'Expeditions pointer',
      'label_display' => '0',
      'provider' => 'layout_builder',
      'view_mode' => 'full',
      'block_revision_id' => $block->getRevisionId(),
      'block_serialized' => NULL,
      'context_mapping' => [],
    ]));
    $settings = $section->getLayoutSettings();
    $settings['label'] = 'Expeditions';
    $section->setLayoutSettings($settings);
    $exp_node->save();
    $placed++;
    print "  filled: 400169 (Natural History Expeditions) with the /baja-expedition pointer\n";
  }
}
// The old expedition slug follows D7's own editorial redirect to the
// current page.
$redirect_repo = \Drupal::service('redirect.repository');
if (!$redirect_repo->findBySourcePath('expedition/baja-gray-whale-expedition')) {
  Redirect::create([
    'redirect_source' => ['path' => 'expedition/baja-gray-whale-expedition', 'query' => []],
    'redirect_redirect' => ['uri' => 'internal:/node/407716'],
    'status_code' => 301,
    'language' => 'und',
  ])->save();
  print "  redirect created: expedition/baja-gray-whale-expedition -> node/407716\n";
}

print "sections filled: $placed\n";
if ($left) {
  print "placeholders left unmapped:\n  " . implode("\n  ", array_unique($left)) . "\n";
}
