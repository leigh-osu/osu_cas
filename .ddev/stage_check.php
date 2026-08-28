<?php
// Does the reported failure still occur on stage?
try {
  $b = \Drupal::entityTypeManager()->getStorage('block_content')->loadRevision(30578);
  print '  block revision 30578: ' . ($b ? 'loaded (' . $b->bundle() . ')' : 'not found') . PHP_EOL;
}
catch (\Throwable $e) {
  print '  block load ERROR: ' . substr($e->getMessage(), 0, 140) . PHP_EOL;
}
try {
  $n = \Drupal::entityTypeManager()->getStorage('node')->load(241001);
  $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($n, 'full');
  $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
  printf("  node 241001 rendered: %d bytes\n", strlen($html));
}
catch (\Throwable $e) {
  print '  render ERROR: ' . substr($e->getMessage(), 0, 140) . PHP_EOL;
}
