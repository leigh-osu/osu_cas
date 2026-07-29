<?php

namespace Drupal\osu_migrations_media\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Provies a process plugin for remote video URIs.
 *
 * The `MediaRemoteVideo` plugin modifies input URIs from specific remote video
 * services (e.g., YouTube, Vimeo, MediaSpace) to their standard web-accessible
 * formats.
 *
 * This class overrides the `transform` method from its base class to perform
 * URI manipulation using regular expressions. It ensures compatibility with
 * standard web URL formats required during migration processing.
 *
 * Features:
 * - Converts YouTube URIs beginning with `youtube://v/` to
 * `https://www.youtube.com/watch?v=`.
 * - Converts Vimeo URIs beginning with `vimeo://v/` to `https://vimeo.com/`.
 * - Handles MediaSpace URIs beginning with `mediaspace://v/` by converting
 * them to `https://`.
 *
 * @code
 * process:
 *   plugin: media_remote_video
 * @endcode
 * /
 */
#[MigrateProcess(
  id: 'media_remote_video'
)]
class MediaRemoteVideo extends ProcessPluginBase {

  /**
   * {@inheritDoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $update_uri_scheme = preg_replace([
      '/^youtube:\/\/v\//i',
      '/^vimeo:\/\/v\//i',
      '/^mediaspace:\/\/v\//i',
    ], [
      'https://www.youtube.com/watch?v=',
      'https://vimeo.com/',
      'https://',
    ], $value);
    return $update_uri_scheme;
  }

}
