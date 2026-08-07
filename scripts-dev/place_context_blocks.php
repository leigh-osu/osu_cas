<?php

/**
 * @file
 * Recreate D7 Context-module custom block placements.
 *
 * D7 placed nine custom blocks via Context (the block table rows are
 * disabled). The blocks migrated as reusable basic block_content with
 * their D7 bids as ids; this recreates the placements:
 *
 * - The post_content social widget becomes manzanita block.block config
 *   with entity_bundle visibility (post_content -> pre_footer). The D7
 *   full_top contexts (MES onion/wildflower headers) are NOT recreated:
 *   larch has no full_top region, so D7 never rendered them.
 * - pre_content contexts rendered INSIDE the content column under the
 *   title on D7, so they become Layout Builder insertions at the top of
 *   the body column instead; their D7 path wildcards are resolved to
 *   node lists via the D7 alias table (rerun the script after new
 *   Source issues migrate).
 * - Contexts that listed explicit node/NID paths become Layout Builder
 *   insertions too: content region appends a section; sidebar_first
 *   converts the body section to 67/33 and puts the block in the right
 *   column (weight -10, above any existing sidebar block).
 *
 * Contexts placing views/menu blocks are out of scope (those D7 views
 * were not rebuilt 1:1). D7's block:7 reactions reference a deleted
 * block and render nothing there either.
 *
 * Idempotent; runs at the end of rebuild_site.sh section 7.
 *
 * Run: ddev drush --uri=https://osu-cas.ddev.site scr scripts-dev/place_context_blocks.php
 */

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;

$uuid_service = \Drupal::service('uuid');

$block_uuid = function (int $id): ?string {
  $b = BlockContent::load($id);
  return $b ? $b->uuid() : NULL;
};

// ---- Part A: theme block placements (path/type visibility) ----
$placements = [
  'cas_ctx_social_media_widget' => [
    'block' => 47,
    'region' => 'pre_footer',
    'weight' => -10,
    'visibility' => ['entity_bundle:node' => ['bundles' => ['art_about_agriculture' => 'art_about_agriculture', 'project' => 'project', 'story' => 'story']]],
  ],
];

foreach ($placements as $id => $def) {
  if (Block::load($id)) {
    continue;
  }
  $uuid = $block_uuid($def['block']);
  if (!$uuid) {
    print "WARN $id: block_content {$def['block']} missing\n";
    continue;
  }
  $visibility = [];
  foreach ($def['visibility'] as $plugin => $cfg) {
    $visibility[$plugin] = $cfg + [
      'id' => $plugin,
      'negate' => FALSE,
    ];
    if ($plugin === 'entity_bundle:node') {
      $visibility[$plugin]['context_mapping'] = ['node' => '@node.node_route_context:node'];
    }
  }
  Block::create([
    'id' => $id,
    'theme' => 'manzanita',
    'region' => $def['region'],
    'weight' => $def['weight'],
    'plugin' => 'block_content:' . $uuid,
    'settings' => [
      'id' => 'block_content:' . $uuid,
      'label' => BlockContent::load($def['block'])->label(),
      'label_display' => '0',
      'provider' => 'block_content',
      'status' => TRUE,
      'info' => '',
      'view_mode' => 'full',
    ],
    'visibility' => $visibility,
  ])->save();
  print "placed $id (block {$def['block']}, {$def['region']})\n";
}

// ---- Part B: Layout Builder insertions ----
// Resolve D7 path patterns (aliases and node/NID paths) to node ids.
$d7 = \Drupal\Core\Database\Database::getConnection('default', 'migrate');
$resolve_paths = function (array $patterns) use ($d7): array {
  $nids = [];
  foreach ($patterns as $pattern) {
    if (preg_match('~^node/(\d+)$~', $pattern, $m)) {
      $nids[$m[1]] = TRUE;
      continue;
    }
    $like = str_replace(['%', '*'], ['\%', '%'], $pattern);
    foreach ($d7->query("SELECT source FROM url_alias WHERE alias LIKE :a", [':a' => $like]) as $r) {
      if (preg_match('~^node/(\d+)$~', $r->source, $m)) {
        $nids[$m[1]] = TRUE;
      }
    }
  }
  return array_map('intval', array_keys($nids));
};

$lb_targets = [
  // the_source: masthead under the title on every Source/newsletter page.
  ['block' => 6, 'mode' => 'body_top', 'nids' => $resolve_paths(['main/source/*', 'newsletter/*', 'main/newsletter/*', 'source/*', 'thesource/*', 'newsroom/source*'])],
  // social_media_template: header on the social-media pages.
  ['block' => 206, 'mode' => 'body_top', 'nids' => $resolve_paths(['social-media/*', 'main/social-media-and-multimedia', 'main/newsletters'])],
  // weather_links: home link atop the yearly weather pages.
  ['block' => 301, 'mode' => 'body_top', 'nids' => $resolve_paths(['malheur/weather/yearly/*', 'malheur/weather/yearly-summary/*'])],
  // bmsb-landing-page: BMSB News after the landing content.
  ['block' => 96, 'mode' => 'append', 'nids' => [36676]],
  // emt_academics: Academics menu + EMT AP Contacts in the right sidebar
  // (D7's aside stacked the menu block above the contacts).
  ['block' => 336, 'mode' => 'sidebar', 'label_display' => '0', 'menu' => ['plugin' => 'system_menu_block:menu-academics', 'label' => 'Academics'], 'nids' => [217606, 217611, 217616, 217621, 217626, 217631, 217636, 217641, 217646, 217651, 217716, 217721, 217731, 217736, 217751, 217756, 217761, 217766, 217771, 217776, 217781, 217786, 217791, 217801, 217806, 217946, 217951, 218111, 219776, 212141, 225791]],
  // nw_plant_eval: program menu in the right sidebar (titled, like D7's h2).
  ['block' => 171, 'mode' => 'sidebar', 'label_display' => '1', 'nids' => [49351, 49671, 49711, 49736, 49741, 49761]],
];

$sidebar_settings = [
  'breakpoints' => [
    'extra_wide_desktop' => 'blb_col_2_67_33',
    'desktop' => 'blb_col_2_67_33',
    'tablet' => 'blb_col_2_67_33',
    'mobile' => 'blb_col_1_full_width',
  ],
  'layout_regions_classes' => [
    'blb_region_col_1' => ['col-xxl-8', 'col-lg-8', 'col-md-8', 'col-12'],
    'blb_region_col_2' => ['col-xxl-4', 'col-lg-4', 'col-md-4', 'col-12'],
  ],
  'container' => 'container',
  'remove_gutters' => '0',
];

// Choose the section a sidebar belongs beside: the body section when the
// body actually has content (field body non-empty, or a converted 'Body'
// inline block); otherwise the last paragraph-content section, so the
// sidebar does not float beside an empty body column.
$find_sidebar_delta = function ($node, $layout): ?int {
  $body_delta = NULL;
  $body_is_inline = FALSE;
  $last_para = NULL;
  foreach ($layout->getSections() as $delta => $section) {
    foreach ($section->getComponents() as $c) {
      $cfg = $c->get('configuration');
      $label = $cfg['label'] ?? '';
      if ($cfg['id'] === 'field_block:node:page:body' || $label === 'Body') {
        $body_delta = $delta;
        $body_is_inline = $label === 'Body';
      }
      elseif ($cfg['id'] === 'inline_block:paragraph_block' && $label !== 'Right sidebar') {
        $last_para = $delta;
      }
    }
  }
  if ($body_delta !== NULL && ($body_is_inline || !$node->get('body')->isEmpty())) {
    return $body_delta;
  }
  return $last_para ?? $body_delta;
};

// Ensure a menu block sits above a placed sidebar block (D7's aside
// stacked e.g. the Academics menu above the EMT contacts).
$ensure_menu = function ($node, array $target) use ($uuid_service) {
  $layout = $node->get('layout_builder__layout');
  $menu_plugin = $target['menu']['plugin'];
  foreach ($layout->getSections() as $section) {
    foreach ($section->getComponents() as $c) {
      if ($c->getPluginId() === $menu_plugin) {
        return;
      }
    }
  }
  // Find the section holding the target block in col_2.
  $block_uuid_str = NULL;
  foreach ($layout->getSections() as $delta => $section) {
    foreach ($section->getComponents() as $c) {
      if (str_starts_with($c->getPluginId(), 'block_content:') && $c->getRegion() === 'blb_region_col_2') {
        $menu = new SectionComponent($uuid_service->generate(), 'blb_region_col_2', [
          'id' => $menu_plugin,
          'label' => $target['menu']['label'],
          'label_display' => 'visible',
          'provider' => 'system',
          'level' => 1,
          'depth' => 0,
          'expand_all_items' => FALSE,
          'context_mapping' => [],
        ]);
        $section->appendComponent($menu);
        $menu->setWeight(-20);
        $node->save();
        print "MENU {$node->id()}: {$target['menu']['label']} menu added\n";
        return;
      }
    }
  }
};

$inserted = 0;
foreach ($lb_targets as $target) {
  $uuid = $block_uuid($target['block']);
  $block = BlockContent::load($target['block']);
  if (!$uuid) {
    print "WARN: block_content {$target['block']} missing\n";
    continue;
  }
  $plugin_id = 'block_content:' . $uuid;
  foreach ($target['nids'] as $nid) {
    $node = Node::load($nid);
    if (!$node || !$node->hasField('layout_builder__layout') || $node->get('layout_builder__layout')->isEmpty()) {
      print "SKIP $nid: no layout (" . ($node ? $node->bundle() : 'missing') . ")\n";
      continue;
    }
    $layout = $node->get('layout_builder__layout');
    $present = FALSE;
    $present_delta = NULL;
    foreach ($layout->getSections() as $delta => $section) {
      foreach ($section->getComponents() as $existing) {
        if ($existing->getPluginId() === $plugin_id) {
          $present = TRUE;
          $present_delta = $delta;
          // Converge weights from runs before the appendComponent() fix.
          $want = $target['mode'] === 'body_top' ? -20 : -10;
          if ($target['mode'] !== 'append' && $existing->getWeight() !== $want) {
            $existing->setWeight($want);
            $node->save();
            print "WEIGHT $nid ({$node->label()}): block {$target['block']} -> $want\n";
          }
          break 2;
        }
      }
    }
    if ($present && $target['mode'] === 'sidebar') {
      // Converge placement: earlier runs put the sidebar beside the body
      // section even when the body is empty. Relocate to the right section
      // and collapse the emptied 67/33 row back to one column.
      $desired = $find_sidebar_delta($node, $layout);
      if ($desired !== NULL && $desired !== $present_delta) {
        $sections = $layout->getSections();
        $old_section = $sections[$present_delta];
        $keep = [];
        $col2_left = 0;
        foreach ($old_section->getComponents() as $c) {
          if ($c->getPluginId() === $plugin_id) {
            continue;
          }
          $region = $c->getRegion() === 'blb_region_col_2' ? 'blb_region_col_2' : 'blb_region_col_1';
          $col2_left += $region === 'blb_region_col_2' ? 1 : 0;
          $moved = new SectionComponent($c->getUuid(), $region, $c->get('configuration'), $c->toArray()['additional'] ?? []);
          $moved->setWeight($c->getWeight());
          $keep[$moved->getUuid()] = $moved;
        }
        $layout->removeSection($present_delta);
        if ($col2_left === 0) {
          foreach ($keep as $c) {
            $c->setRegion('blb_region_col_1');
          }
          $layout->insertSection($present_delta, new Section('bootstrap_layout_builder:blb_col_1', ['container' => 'container'], $keep));
        }
        else {
          $layout->insertSection($present_delta, new Section('bootstrap_layout_builder:blb_col_2', $old_section->getLayoutSettings(), $keep));
        }
        $node->save();
        print "MOVE $nid ({$node->label()}): block {$target['block']} leaving empty-body row\n";
        $present = FALSE;
      }
    }
    if ($present) {
      if ($target['mode'] === 'sidebar' && !empty($target['menu'])) {
        $ensure_menu($node, $target);
      }
      continue;
    }
    $component = new SectionComponent($uuid_service->generate(), 'blb_region_col_1', [
      'id' => $plugin_id,
      'label' => $block->label(),
      'label_display' => $target['label_display'] ?? '0',
      'provider' => 'block_content',
      'status' => TRUE,
      'info' => '',
      'view_mode' => 'full',
      'context_mapping' => [],
    ]);
    $component->setWeight(-10);

    if ($target['mode'] === 'body_top') {
      // Into the body section's left/main column, above the body block.
      $body_delta = NULL;
      foreach ($layout->getSections() as $delta => $section) {
        foreach ($section->getComponents() as $c) {
          $cfg = $c->get('configuration');
          if ($cfg['id'] === 'field_block:node:page:body' || ($cfg['label'] ?? '') === 'Body') {
            $body_delta = $delta;
            break 2;
          }
        }
      }
      if ($body_delta === NULL) {
        print "SKIP $nid ({$node->label()}): no body section\n";
        continue;
      }
      // appendComponent() reassigns the weight to last+1; set it after.
      $layout->getSections()[$body_delta]->appendComponent($component);
      $component->setWeight(-20);
    }
    elseif ($target['mode'] === 'append') {
      $layout->appendSection(new Section('bootstrap_layout_builder:blb_col_1', ['container' => 'container'], [$component->getUuid() => $component]));
    }
    else {
      // Sidebar: beside the section that actually carries the content.
      $body_delta = $find_sidebar_delta($node, $layout);
      if ($body_delta === NULL) {
        print "SKIP $nid ({$node->label()}): no body section\n";
        continue;
      }
      $old = $layout->getSections()[$body_delta];
      $component->setRegion('blb_region_col_2');
      if ($old->getLayoutId() === 'bootstrap_layout_builder:blb_col_2') {
        // Already two-column (e.g. a converted right sidebar): drop the
        // block into the existing right column, above its content.
        // (appendComponent() reassigns the weight; set it after.)
        $old->appendComponent($component);
        $component->setWeight(-10);
      }
      else {
        $components = [];
        foreach ($old->getComponents() as $c) {
          $moved = new SectionComponent($c->getUuid(), 'blb_region_col_1', $c->get('configuration'), $c->toArray()['additional'] ?? []);
          $moved->setWeight($c->getWeight());
          $components[$moved->getUuid()] = $moved;
        }
        $components[$component->getUuid()] = $component;
        $layout->removeSection($body_delta);
        $layout->insertSection($body_delta, new Section('bootstrap_layout_builder:blb_col_2', $sidebar_settings, $components));
      }
    }
    $node->save();
    if ($target['mode'] === 'sidebar' && !empty($target['menu'])) {
      $ensure_menu($node, $target);
    }
    $inserted++;
    print "OK $nid ({$node->label()}): block {$target['block']} placed\n";
  }
}
print "Done: $inserted layout insertions.\n";
