<?php

/**
 * @file
 * Drop btn-sm / btn-lg from button markup, leaving every button at one size.
 *
 * D7 offered small, default and large buttons and the migration carried the
 * choice across: normalize_legacy_buttons.php maps btn-large/small/mini onto
 * Bootstrap's btn-lg/btn-sm alongside the CAS scheme, so content arrived with
 * 362 large and 377 small buttons. CAS now uses one button size, and the
 * editor's size picker is being removed with it, so the existing markup is
 * brought into line rather than left as the only way to get a sized button.
 *
 * Only the size class is touched. The scheme classes (cas-button-dark and
 * cas-button-light), the base .btn, and everything else on the element are
 * left exactly as they are — every sized button in this database is already
 * cas-button-dark, so nothing here changes a colour.
 *
 * Restricted to class attributes that also carry `btn`, so an unrelated
 * btn-sm-looking token elsewhere in the markup cannot be caught. Idempotent.
 *
 * Usage: drush scr scripts-dev/strip_button_sizes.php -- --dry-run
 *        drush scr scripts-dev/strip_button_sizes.php
 */

$dry = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$db = \Drupal::database();

// Every long-text column that carries the classes, found by scanning rather
// than assumed: block bodies hold the bulk of them, node bodies a handful, and
// four accordion paragraphs.
$targets = [
  'block_content__body' => 'body_value',
  'node__body' => 'body_value',
  'paragraph__field_p_accordion_body' => 'field_p_accordion_body_value',
  // Layout Builder pins each inline block into a layout by block_revision_id,
  // so for those blocks the revision row is what renders, not the current one.
  // Updating only the current table leaves the page unchanged — which is
  // exactly what happened the first time this ran.
  'block_content_revision__body' => 'body_value',
];

/**
 * Removes btn-sm / btn-lg from any class list that also contains btn.
 */
$strip = function (string $html, int &$buttons): string {
  return preg_replace_callback(
    '~\bclass\s*=\s*(["\'])([^"\']*)\1~i',
    function ($m) use (&$buttons) {
      $classes = preg_split('~\s+~', trim($m[2]), -1, PREG_SPLIT_NO_EMPTY);
      if (!in_array('btn', $classes, TRUE)) {
        return $m[0];
      }
      $kept = array_values(array_filter($classes, fn($c) => $c !== 'btn-sm' && $c !== 'btn-lg'));
      if (count($kept) === count($classes)) {
        return $m[0];
      }
      $buttons++;
      return 'class=' . $m[1] . implode(' ', $kept) . $m[1];
    },
    $html
  );
};

$rows_changed = $buttons_changed = 0;
foreach ($targets as $table => $column) {
  if (!$db->schema()->tableExists($table)) {
    printf("  %-40s no such table\n", $table);
    continue;
  }
  // Revision tables key on revision_id; field tables on entity_id.
  $key = str_contains($table, '_revision__') ? 'revision_id'
    : ($db->schema()->fieldExists($table, 'entity_id') ? 'entity_id' : 'id');
  $rows = $db->query('SELECT `' . $key . '` AS k, `' . $column . '` AS v FROM {' . $table . '} WHERE `' . $column . "` REGEXP 'btn-(sm|lg)'")->fetchAll();
  $table_rows = $table_buttons = 0;
  foreach ($rows as $row) {
    $count = 0;
    $new = $strip((string) $row->v, $count);
    if (!$count || $new === $row->v) {
      continue;
    }
    $table_rows++;
    $table_buttons += $count;
    if (!$dry) {
      $db->update($table)->fields([$column => $new])->condition($key, $row->k)->execute();
    }
  }
  printf("  %-40s %3d row(s), %3d button(s)\n", $table, $table_rows, $table_buttons);
  $rows_changed += $table_rows;
  $buttons_changed += $table_buttons;
}

printf(
  "\n%s %d button(s) across %d row(s)\n",
  $dry ? 'Would resize' : 'Resized', $buttons_changed, $rows_changed
);
if (!$dry && $rows_changed) {
  \Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list', 'block_content_list', 'paragraph_list']);
  print "Invalidated content cache tags — run `drush cr` for a full rebuild.\n";
}
