<?php

namespace Drupal\osu_migrations;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\migrate\MigrateLookupInterface;

/**
 * Class responsible for migrating missing files referenced in text content.
 *
 * This class scans text content for references to local files and attempts to
 * copy them if they are missing. Files that are found are imported and
 * managed as Drupal entities.
 */
class OsuMigrateMissingFiles {

  /**
   * The Migrate lookup interface.
   *
   * @var \Drupal\migrate\MigrateLookupInterface
   */
  private MigrateLookupInterface $lookup;

  /**
   * The Entity type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The File system Interface.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private FileSystemInterface $fileSystem;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private LoggerChannelFactoryInterface $logger;

  /**
   * Constructor method.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager interface.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system interface.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->logger = $logger;
  }

  /**
   * Processes missing files referenced in the given value and attempts to
   * migrate them if necessary.
   *
   * This method scans the provided text for links to local files, including
   * images, and checks if they have already been migrated. If the files are
   * not found in the system, it attempts to copy the file and add it as a
   * managed file.
   *
   * @param string $value
   *   The text to scan for references to local files.
   *
   * @return void
   *   No return value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function copyMissingFiles(string $value): void {
    // Create two named capture groups for the relative path and the file URI.
    $pattern = '/(href|src)=\s*["\'](?<relativePath>\/sites\/[a-zA-Z0-9\.]*\/files\/(?<uri>.*))["\']/isU';
    // Scan the text for matches and store them in an associative array.
    preg_match_all($pattern, $value, $matches);
    // If we found any matches, attempt to copy and import the files.
    if ($matches && count($matches['uri']) > 0) {
      foreach ($matches['uri'] as $key => $fileUri) {
        $publicUri = "public://{$fileUri}";
        $foundFiles = $this->entityTypeManager->getStorage('file')
          ->loadByProperties(['uri' => $publicUri]);
        if ($foundFiles && count($foundFiles) > 0) {
          continue;
        }
        else {
          $this->copyAndImportFile($matches['relativePath'][$key], $matches['uri'][$key]);
        }
      }
    }
  }

  /**
   * Moves a file to a specified directory and imports it as a managed file.
   *
   * @param string $relativePath
   *   The relative path of the file to be moved, based on the Drupal root.
   * @param string $fileUri
   *   The target file URI where the file should be moved to within the public
   *   directory.
   *
   * @return void
   *   This method does not return a value.
   */
  private function copyAndImportFile(string $relativePath, string $fileUri): void {
    // Ensure the Destination directory exists.
    $containerDir = dirname($fileUri);
    if ($containerDir === '.') {
      $destinationDirUri = 'public://';
    }
    else {
      $destinationDirUri = "public://{$containerDir}";
    }
    $this->fileSystem->prepareDirectory($destinationDirUri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    try {
      $copiedFileUri = $this->fileSystem->copy(DRUPAL_ROOT . $relativePath, $destinationDirUri, FileExists::Replace);
    }
    catch (\Exception $e) {
      $this->logger->get('osu_migrations')
        ->warning("Could not copy file $relativePath");
      return;
    }
    $copiedFileObject = File::create(['uri' => $copiedFileUri, 'status' => 1]);
    $copiedFileObject->setPermanent();
    $copiedFileObject->save();
    $mimeType = $copiedFileObject->getMimeType();
    if (str_starts_with($mimeType, 'image/')) {
      Media::create([
        'bundle' => 'image',
        'field_media_image' => $copiedFileObject,
      ])
        ->save();
    }
    elseif (str_starts_with($mimeType, 'video/')) {
      // Create local video.
      Media::create([
        'bundle' => 'local_video',
        'field_media_file' => $copiedFileObject,
      ])
        ->save();
    }
    elseif (str_starts_with($mimeType, 'application/') || str_starts_with($mimeType, 'text/')) {
      // Create Document.
      Media::create([
        'bundle' => 'file',
        'field_media_file' => $copiedFileObject,
      ])
        ->save();
    }
  }

}
