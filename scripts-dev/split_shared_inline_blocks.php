<?php

/**
 * @file
 * Give each page its own copy of an inline block shared between pages.
 *
 * The migration occasionally pointed the Layout Builder sections of several
 * duplicate pages at the same non-reusable block revision. inline_block_usage
 * can only name one host per block, and when that host page is deleted core's
 * cron deletes the block from under the other pages. This script finds every
 * block referenced from the CURRENT layout of more than one host, keeps it on
 * the host recorded in inline_block_usage (or the lowest id), and gives every
 * other host a fresh duplicate block via the normal Layout Builder save path,
 * which also records the new block's usage row.
 *
 * Each non-host page gets one new node revision with a log message. Nothing
 * is deleted. A CSV of old block -> new block per page is written beside this
 * script (or BACKFILL_LOG_DIR, or the system temp dir).
 *
 * Usage:
 *   drush --uri=... scr scripts-dev/split_shared_inline_blocks.php          # dry run
 *   drush --uri=... scr scripts-dev/split_shared_inline_blocks.php apply    # save duplicates
 */

use Drupal\layout_builder\Plugin\Block\InlineBlock;

$apply = in_array('apply', $extra ?? [], TRUE);
$db = \Drupal::database();
$etm = \Drupal::entityTypeManager();

$sources = [
  'node__layout_builder__layout' => 'node',
  'group__layout_builder__layout' => 'group',
];

// block revision id => [entity_type => [entity_id => TRUE]]
$hosts = [];
foreach ($sources as $table => $type) {
  if (!$db->schema()->tableExists($table)) {
    continue;
  }
  $rows = $db->query("SELECT entity_id, layout_builder__layout_section AS section FROM {{$table}} WHERE layout_builder__layout_section LIKE '%block_revision_id%'");
  foreach ($rows as $row) {
    if (preg_match_all('/s:17:"block_revision_id";(?:i:(\d+)|s:\d+:"(\d+)")/', $row->section, $m)) {
      foreach ($m[1] as $k => $v) {
        $rid = (int) ($v !== '' ? $v : $m[2][$k]);
        if ($rid) {
          $hosts[$rid][$type][(int) $row->entity_id] = TRUE;
        }
      }
    }
  }
}

// Group revisions by block, keep only blocks with more than one distinct host.
$rev_to_block = [];
foreach (array_chunk(array_keys($hosts), 2000) as $chunk) {
  foreach ($db->query("SELECT revision_id, id FROM {block_content_revision} WHERE revision_id IN (:r[])", [':r[]' => $chunk]) as $r) {
    $rev_to_block[(int) $r->revision_id] = (int) $r->id;
  }
}
$block_hosts = [];
foreach ($hosts as $rid => $types) {
  if (!isset($rev_to_block[$rid])) {
    continue;
  }
  foreach ($types as $type => $ids) {
    foreach (array_keys($ids) as $id) {
      $block_hosts[$rev_to_block[$rid]]["$type:$id"] = ['type' => $type, 'id' => $id, 'rid' => $rid];
    }
  }
}
$shared = array_filter($block_hosts, fn($h) => count($h) > 1);
printf("blocks referenced from the current layout of more than one host: %d\n", count($shared));
if (!$shared) {
  return;
}

$usage = \Drupal::service('inline_block.usage');
$block_storage = $etm->getStorage('block_content');
$plan = [];
foreach ($shared as $bid => $host_list) {
  $block = $block_storage->load($bid);
  if (!$block || $block->isReusable()) {
    printf("  block %d: skipped (%s)\n", $bid, $block ? 'reusable' : 'missing');
    continue;
  }
  $u = $usage->getUsage($bid);
  $keep = NULL;
  if ($u && isset($host_list["{$u->layout_entity_type}:{$u->layout_entity_id}"])) {
    $keep = "{$u->layout_entity_type}:{$u->layout_entity_id}";
  }
  else {
    ksort($host_list, SORT_NATURAL);
    $keep = array_key_first($host_list);
  }
  printf("  block %d \"%s\" (%s): keep on %s; copy for %s\n", $bid, $block->label(), $block->bundle(), $keep, implode(', ', array_diff(array_keys($host_list), [$keep])));
  foreach ($host_list as $key => $h) {
    if ($key !== $keep) {
      $plan[$h['type']][$h['id']][] = ['bid' => $bid, 'rid' => $h['rid']];
    }
  }
}

if (!$apply) {
  echo "DRY RUN — pass 'apply' to create the copies.\n";
  return;
}

$env = getenv('AH_SITE_ENVIRONMENT') ?: 'local';
$log_dir = getenv('BACKFILL_LOG_DIR') ?: __DIR__;
if (!is_writable($log_dir)) {
  $log_dir = sys_get_temp_dir();
}
$csv_path = sprintf('%s/split_shared_inline_blocks_%s_%s.csv', $log_dir, $env, date('Ymd_His'));
$csv = fopen($csv_path, 'w');
if (!$csv) {
  echo "FAILED: cannot open $csv_path; nothing changed.\n";
  return;
}
fputcsv($csv, ['host_type', 'host_id', 'host_new_revision', 'old_block_id', 'old_block_revision', 'new_block_id', 'new_block_revision']);

foreach ($plan as $type => $entities) {
  $storage = $etm->getStorage($type);
  foreach ($entities as $id => $replacements) {
    $entity = $storage->load($id);
    $wanted = array_column($replacements, 'bid', 'rid');
    $replaced = [];
    foreach ($entity->get('layout_builder__layout') as $item) {
      foreach ($item->section->getComponents() as $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof InlineBlock) {
          continue;
        }
        $conf = $plugin->getConfiguration();
        $rid = (int) ($conf['block_revision_id'] ?? 0);
        if (!isset($wanted[$rid])) {
          continue;
        }
        $dup = $block_storage->loadRevision($rid)->createDuplicate();
        $dup->setNonReusable();
        $conf['block_revision_id'] = NULL;
        $conf['block_serialized'] = serialize($dup);
        $component->setConfiguration($conf);
        $replaced[$rid] = $wanted[$rid];
      }
    }
    if (!$replaced) {
      printf("  %s %d: no matching component found, skipped\n", $type, $id);
      continue;
    }
    $entity->setNewRevision(TRUE);
    $entity->setRevisionUserId(1);
    $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $entity->setRevisionLogMessage('Replaced inline block(s) shared with another page by this page\'s own copy: block ' . implode(', ', array_values($replaced)) . '.');
    $entity->save();

    // Read back the new block revision ids from the saved layout.
    $new = [];
    foreach ($entity->get('layout_builder__layout') as $item) {
      foreach ($item->section->getComponents() as $component) {
        $plugin = $component->getPlugin();
        if ($plugin instanceof InlineBlock) {
          $c = $plugin->getConfiguration();
          if (!empty($c['block_revision_id']) && !isset($hosts[(int) $c['block_revision_id']])) {
            $nb = $block_storage->loadRevision($c['block_revision_id']);
            $new[$nb->id()] = (int) $c['block_revision_id'];
          }
        }
      }
    }
    foreach ($replaced as $old_rid => $old_bid) {
      $new_bid = $new ? array_key_first($new) : NULL;
      $new_rid = $new_bid ? $new[$new_bid] : NULL;
      unset($new[$new_bid]);
      $u = $new_bid ? $usage->getUsage($new_bid) : NULL;
      printf("  %s %d rev %d: block %d (rev %d) -> new block %s (rev %s), usage %s\n", $type, $id, $entity->getRevisionId(), $old_bid, $old_rid, $new_bid ?? '?', $new_rid ?? '?', $u ? "{$u->layout_entity_type}:{$u->layout_entity_id}" : 'MISSING');
      fputcsv($csv, [$type, $id, $entity->getRevisionId(), $old_bid, $old_rid, $new_bid, $new_rid]);
    }
  }
}
fclose($csv);
echo "log: $csv_path\n";
