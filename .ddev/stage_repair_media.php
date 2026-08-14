<?php
// Repoint redesign media at the files the export intends. The database was
// pushed to stage before the local repair, so it still carries the wrong
// references left by the import's exact-URI matching (fixed in 729f82afe).
$db = \Drupal::database();
$path = '/var/www/html/osucas.stage/scripts-dev/stage_redesigns/redesigns.json';
$data = json_decode(file_get_contents($path), TRUE);
$ms = \Drupal::entityTypeManager()->getStorage('media');
$fixed = $skipped = $ok = 0;
foreach ($data['media'] as $m) {
  $found = $ms->loadByProperties(['uuid' => $m['uuid']]);
  if (!$found) {
    continue;
  }
  $local = reset($found);
  $want_fid = NULL;
  foreach ($m['fields'] as $items) {
    foreach ($items as $it) {
      if (isset($it['target_id'])) {
        $want_fid = (string) $it['target_id'];
      }
    }
  }
  if ($want_fid === NULL || !isset($data['files'][$want_fid])) {
    continue;
  }
  $base = basename($data['files'][$want_fid]['uri']);
  if (!$local->hasField('field_media_image') || $local->get('field_media_image')->isEmpty()) {
    continue;
  }
  $have = $local->get('field_media_image')->entity;
  if ($have && strtolower(basename($have->getFileUri())) === strtolower($base)) {
    $ok++;
    continue;
  }
  $cands = $db->query('SELECT fid, uri FROM {file_managed} WHERE uri = :e OR uri LIKE :s',
    [':e' => 'public://' . $base, ':s' => '%/' . $base])->fetchAll();
  if (count($cands) !== 1) {
    printf("  !! %s: %d candidates - left alone\n", $base, count($cands));
    $skipped++;
    continue;
  }
  printf("  media %s: %s -> %s\n", $local->id(),
    $have ? basename($have->getFileUri()) : '(none)', $cands[0]->uri);
  $local->set('field_media_image', ['target_id' => (int) $cands[0]->fid]);
  $local->save();
  $fixed++;
}
printf("  already correct: %d | repaired: %d | skipped: %d\n", $ok, $fixed, $skipped);
