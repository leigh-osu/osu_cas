<?php
/**
 * Replace inline base64 images in long-text fields with managed files.
 *
 * Usage: drush scr scripts-dev/inline_images_to_files.php            (dry run)
 *        drush scr scripts-dev/inline_images_to_files.php -- --apply (write)
 *
 * Walks every longtext *_value column on node/block_content/paragraph field
 * tables, decodes each data:image/...;base64 payload into a managed file under
 * public://inline-images/<entity_type>/<id>/, records file usage against the
 * entity (module "editor", like the editor module does for uploaded images),
 * rewrites the src and saves the entity. Older revisions are left as they are.
 */
$apply = in_array('--apply', $extra ?? [], TRUE);
$db = \Drupal::database();
$etm = \Drupal::entityTypeManager();
$fs = \Drupal::service('file_system');
$usage = \Drupal::service('file.usage');
$urlGen = \Drupal::service('file_url_generator');
$cols = $db->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'longtext' AND COLUMN_NAME LIKE '%_value' AND (TABLE_NAME LIKE 'node\\_\\_%' OR TABLE_NAME LIKE 'block\\_content\\_\\_%' OR TABLE_NAME LIKE 'paragraph\\_\\_%') AND TABLE_NAME NOT LIKE '%revision%'")->fetchAll();
$log = fopen(__DIR__ . '/inline_images_to_files_' . date('Ymd_His') . ($apply ? '' : '_dryrun') . '.csv', 'w');
fputcsv($log, ['entity_type', 'id', 'field', 'images', 'bytes_before', 'bytes_after', 'files']);
$tot = ['entities' => 0, 'images' => 0, 'before' => 0, 'after' => 0, 'failed' => 0];
foreach ($cols as $col) {
  [$entity_type, $field] = explode('__', $col->t, 2);
  $ids = $db->query("SELECT DISTINCT entity_id FROM {{$col->t}} WHERE deleted = 0 AND {$col->c} LIKE '%data:image%'")->fetchCol();
  foreach ($ids as $id) {
    $entity = $etm->getStorage($entity_type)->load($id);
    if (!$entity || !$entity->hasField($field)) { continue; }
    $items = $entity->get($field)->getValue();
    $changed = FALSE; $images = 0; $before = 0; $after = 0; $files = [];
    foreach ($items as $delta => &$item) {
      $html = $item['value'] ?? '';
      if (strpos($html, 'data:image') === FALSE) { continue; }
      $before += strlen($html);
      $n = 0;
      $html = preg_replace_callback('#(src=["\'])data:image/([a-z0-9.+-]+);base64,([^"\']+)(["\'])#i', function ($m) use ($entity_type, $id, $field, $delta, $fs, $usage, $urlGen, $apply, &$n, &$files, &$tot) {
        $ext = strtolower($m[2]) === 'jpeg' ? 'jpg' : preg_replace('/[^a-z0-9]/', '', strtolower($m[2]));
        $data = base64_decode($m[3], TRUE);
        if ($data === FALSE) { $tot['failed']++; return $m[0]; }
        $n++;
        $dir = "public://inline-images/$entity_type/$id";
        $name = $field . '-' . $delta . '-' . $n . '.' . $ext;
        if (!$apply) { $files[] = "$dir/$name (" . strlen($data) . ' bytes)'; return $m[1] . "/[dry-run]/$name" . $m[4]; }
        $fs->prepareDirectory($dir, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);
        $file = \Drupal::service('file.repository')->writeData($data, "$dir/$name", $fs::EXISTS_REPLACE);
        $file->setPermanent(); $file->save();
        $usage->add($file, 'editor', $entity_type, $id);
        $files[] = $file->getFileUri();
        return $m[1] . $urlGen->generateString($file->getFileUri()) . $m[4];
      }, $html);
      if ($n) { $images += $n; $item['value'] = $html; $changed = TRUE; }
      $after += strlen($html);
    }
    unset($item);
    if (!$changed) { continue; }
    $tot['entities']++; $tot['images'] += $images; $tot['before'] += $before; $tot['after'] += $after;
    fputcsv($log, [$entity_type, $id, $field, $images, $before, $after, implode(' | ', $files)]);
    echo str_pad("$entity_type $id $field", 48), " images=$images  ", round($before / 1024), "KB -> ", round($after / 1024), "KB\n";
    if ($apply) {
      $entity->set($field, $items);
      if ($entity instanceof \Drupal\Core\Entity\RevisionableInterface) { $entity->setNewRevision(FALSE); }
      $entity->save();
    }
  }
}
fclose($log);
echo ($apply ? 'APPLIED' : 'DRY RUN'), ": {$tot['entities']} entities, {$tot['images']} images, ", round($tot['before'] / 1048576, 1), "MB -> ", round($tot['after'] / 1024), "KB of field text; undecodable: {$tot['failed']}\n";
