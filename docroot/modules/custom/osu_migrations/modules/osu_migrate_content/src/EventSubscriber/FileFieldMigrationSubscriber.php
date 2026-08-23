<?php

namespace Drupal\osu_migrate_content\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to redirect the lookup of migrated files.
 *
 * Use upgrade_d7_files instead of d7_files.
 */
class FileFieldMigrationSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MigrateEvents::PRE_IMPORT => 'onMigratePreExecute',
    ];
  }

  /**
   * Change the File migration from direct FID to a lookup.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $importEvent
   *   The Migration Importer Event.
   */
  public function onMigratePreExecute(MigrateImportEvent $importEvent): void {
    $migration = $importEvent->getMigration();

    if ($migration->getBaseId() !== 'upgrade_d7_node') {
      return;
    }

    $processes = $migration->getProcess();
    foreach ($processes as &$process) {
      if (is_array($process)) {
        foreach ($process as &$step) {
          if (isset($step['plugin']) && $step['plugin'] === 'sub_process') {
            if (is_array($step['process']) && array_key_exists('target_id', $step['process']) && $step['process']['target_id'] === 'fid') {
              $step['process']['target_id'] = [
                'plugin' => 'migration_lookup',
                'migration' => 'upgrade_d7_files',
                'source' => 'fid',
              ];
            }
          }
        }
      }
    }
    $migration->setProcess($processes);
  }

}
