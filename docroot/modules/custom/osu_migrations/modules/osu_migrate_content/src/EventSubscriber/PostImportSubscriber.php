<?php

namespace Drupal\osu_migrate_content\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * OSU Post Import Event Subscriber.
 */
class PostImportSubscriber implements EventSubscriberInterface {

  /**
   * The database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $connection;

  /**
   * Constructs a new PostImportSubscriber.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The Database Connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    $events[MigrateEvents::POST_IMPORT][] = ['postImport'];
    return $events;
  }

  /**
   * Handles post-import events for migrations.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $postImportEvent
   *   The post-import event object associated with the migration process.
   *
   * @return void
   *   Nothing.
   */
  public function postImport(MigrateImportEvent $postImportEvent): void {
    $migration = $postImportEvent->getMigration();
    if ($migration->getBaseId() === 'upgrade_d7_path_redirect') {
      $this->connection->query("
      DELETE FROM {redirect}
             WHERE EXISTS (
                 SELECT 1 FROM {path_alias}
             WHERE CONCAT('/', {redirect}.redirect_source__path) = {path_alias}.alias
             AND CONCAT('internal:', {path_alias}.path) = {redirect}.redirect_redirect__uri)
       ")->execute();
    }
  }

}
