<?php

/**
 * Print self-contained INSERT statements restoring inline blocks
 * 2245/17396/30546/32472 (2026-09-01 incident) for replay on prod.
 *
 * These four were deleted by dead_video_orphan_cleanup.php's original,
 * flawed orphan test (see that script and the inline-block-orphan-detection
 * lesson): they were live inline blocks on published pages, referenced by
 * block_revision_id in serialized LB sections. Recovery: the overnight
 * backup's block_content* tables were loaded into a scratch DB, the rows
 * re-INSERTed locally with ORIGINAL ids/revision ids (layouts reconnect
 * automatically, no entity-API save — that would mint new revision ids),
 * dead drupal-media embeds stripped from the bodies by direct SQL, then this
 * script dumped the repaired rows for prod:
 *
 *   drush scr scripts-dev/restore_deleted_blocks_sql.php > restore.sql
 *   scp restore.sql <prod>:/mnt/gfs/osucas.prod/tmp/
 *   drush sql:query --file=... && drush cr   (on prod)
 *
 * Applied to prod 2026-09-01; kept as the template for any similar restore
 * (adjust $ids and the table list).
 */

$db = \Drupal::database();
$tables = [
  'block_content' => 'id',
  'block_content_revision' => 'id',
  'block_content_field_data' => 'id',
  'block_content_field_revision' => 'id',
  'block_content__body' => 'entity_id',
  'block_content_revision__body' => 'entity_id',
  'block_content__field_styles' => 'entity_id',
  'block_content_revision__field_styles' => 'entity_id',
];
$ids = [2245, 17396, 30546, 32472];

foreach ($tables as $table => $key) {
  $rows = $db->query("SELECT * FROM {" . $table . "} WHERE $key IN (:ids[])", [':ids[]' => $ids])->fetchAll(\PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
    $cols = '`' . implode('`, `', array_keys($row)) . '`';
    $vals = implode(', ', array_map(function ($v) use ($db) {
      return $v === NULL ? 'NULL' : $db->quote((string) $v);
    }, array_values($row)));
    print "INSERT INTO `$table` ($cols) VALUES ($vals);\n";
  }
}
