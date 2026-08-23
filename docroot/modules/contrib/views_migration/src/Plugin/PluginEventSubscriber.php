<?php

namespace Drupal\views_migration\Plugin;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Event subscriber to forward Migrate events to source and destination plugins.
 */
class PluginEventSubscriber implements EventSubscriberInterface {

  /**
   * The database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * The entity type manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new MyEventSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
  }

  /**
   * Forwards post-import events to the source and destination plugins.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event.
   */
  public function postImport(MigrateImportEvent $event) {
    $migration = $event->getMigration();
    $id = $migration->id();
    $status = $migration->getStatus();
    if ($id == 'd7_views_migration' && $status == 1) {
      $query = $this->database->select('migrate_map_d7_views_migration', 'mmvm');
      $query->addField('mmvm', 'destid1');
      $query->addField('mmvm', 'sourceid1');
      global $argc, $argv;
      $arguments = [];
      if (isset($argv)) {
        foreach ($argv as $value) {
          if (str_contains($value, '--')) {
            $args = explode('=', $value);
            if (count($args) > 1) {
              $arguments[$args[0]] = explode(',', $args[1]);
            }
          }
        }
      }
      if (isset($arguments['--idlist'])) {
        $query->condition('sourceid1', $arguments['--idlist'], 'IN');
      }
      $results = $query->execute();
      $viewStorage = $this->entityTypeManager->getStorage('view');
      while ($result = $results->fetchAssoc()) {
        $view_id = $result['destid1'];
        if (is_null($view_id)) {
          $view_id = $result['sourceid1'];
          if (php_sapi_name() === 'cli') {
            echo "\n\033[31m [Not Migrated]\033[0m - \033[1m{$view_id}\033[0m\n";
          }
          continue;
        }
        else {
          if (php_sapi_name() === 'cli') {
            echo "\n\033[32m [Migrated]\033[0m - \033[1m{$view_id}\033[0m\n";
          }
        }
        $config = $this->configFactory->getEditable('views.view.' . $view_id);
        $displays = $config->get('display');
        foreach ($displays as &$display) {
          if (isset($display['display_options']['header'])) {
            foreach ($display['display_options']['header'] as &$field_value) {
              if (isset($field_value['plugin_id']) && $field_value['plugin_id'] == 'migration_view') {
                $field_value['plugin_id'] = 'view';
                $field_value['field'] = 'view';
              }
            }
          }
        }
        $config->set('display', $displays);
        $config->save();
        $view = $viewStorage->load($view_id);
        if ($view) {
          $view->save();
        }
        else {
          $config->delete();
        }
      }
    }
    if (php_sapi_name() === 'cli') {
      echo "\n";
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[MigrateEvents::POST_IMPORT][] = ['postImport'];
    return $events;
  }

}
