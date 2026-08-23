<?php

namespace Drupal\osu_migrations_shurly\Plugin\migrate\destination;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateDestination;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a ShURLy destination plugin.
 */
#[MigrateDestination(
  id: 'shurly_history'
)]
class OsuMigrationsShurlyHistory extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  /**
   * Construct a ShURLy History Destination row.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration entity.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->database = $database;
    $this->supportsRollback = TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): OsuMigrationsShurlyHistory|static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('database')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getIds(): array {
    return ['hid' => ['type' => 'integer']];
  }

  /**
   * {@inheritDoc}
   */
  public function import(Row $row, array $old_destination_id_values = []): bool|array {
    $record = [];
    $record['hid'] = $row->getSourceProperty('hid');
    $record['rid'] = $row->getSourceProperty('rid');
    $record['vid'] = $row->getSourceProperty('vid');
    $record['source'] = $row->getSourceProperty('source');
    $record['destination'] = $row->getSourceProperty('destination');
    $record['last_date'] = $row->getSourceProperty('last_date');
    $record['count'] = $row->getSourceProperty('count');
    return [
      $this->database->insert('shurly_history')
        ->fields($record)
        ->execute(),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [
      'hid' => $this->t('The history ID.'),
      'rid' => $this->t('The redirect ID.'),
      'vid' => $this->t('The version ID.'),
      'source' => $this->t('The source URL.'),
      'destination' => $this->t('The destination URL.'),
      'last_date' => $this->t('The last date.'),
      'count' => $this->t('The count.'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function rollback(array $destination_identifier) {
    $this->database->delete('shurly_history')
      ->condition('hid', $destination_identifier['hid'])
      ->execute();
  }

}
