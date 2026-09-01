<?php

/**
 * Apply-mode wrapper for dead_video_orphan_cleanup.php: remote `drush scr`
 * refuses extra script arguments, so this sets the flag and includes it.
 */

$extra = ['delete'];
require __DIR__ . '/dead_video_orphan_cleanup.php';
