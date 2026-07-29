<?php

namespace Drupal\osu_migrate_content\EventSubscriber;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Node migration event subscriber.
 */
class NodeTextFormatMigrationSubscriber implements EventSubscriberInterface {

  /**
   * The Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private EntityFieldManagerInterface $entityFieldManager;

  /**
   * Constructor injects necessary dependencies.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The Entity Field Manager Interface.
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager) {
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MigrateEvents::PRE_IMPORT => 'onPreImport',
    ];
  }

  /**
   * Update Text format for body fields on node migration.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $importEvent
   *   The Migration Import Event.
   */
  public function onPreImport(MigrateImportEvent $importEvent): void {
    $migration = $importEvent->getMigration();

    if ($migration->getBaseId() !== 'upgrade_d7_node') {
      return;
    }
    $destinationConfig = $migration->getDestinationConfiguration();

    $entityType = '';
    if (str_contains($destinationConfig['plugin'], ':')) {
      [, $entityType] = explode(':', $destinationConfig['plugin']);
    }

    $bundle = $destinationConfig['default_bundle'] ?? '';
    $processes = $migration->getProcess();

    $fields = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
    foreach ($processes as $fieldName => $process) {
      if (!isset($fields[$fieldName])) {
        continue;
      }
      $fieldType = $fields[$fieldName]->getType();
      if ($fieldType === 'text_with_summary') {
        $processes[$fieldName][0]['plugin'] = 'sub_process';
        $processes[$fieldName][0]['process'] = [
          'value' => [
            'plugin' => 'osu_media_wysiwyg_filter',
            'source' => 'value',
          ],
          'summary' => [
            'plugin' => 'get',
            'source' => 'summary',
          ],
          'format' => [
            'plugin' => 'default_value',
            'default_value' => 'full_html',
          ],
        ];
      }
      elseif ($fieldType === 'text_long') {
        $processes[$fieldName][0]['plugin'] = 'sub_process';
        $processes[$fieldName][0]['process'] = [
          'value' => [
            'plugin' => 'osu_media_wysiwyg_filter',
            'source' => 'value',
          ],
          'format' => [
            'plugin' => 'default_value',
            'default_value' => 'full_html',
          ],
        ];
      }
    }
    $migration->setProcess($processes);
  }

}
