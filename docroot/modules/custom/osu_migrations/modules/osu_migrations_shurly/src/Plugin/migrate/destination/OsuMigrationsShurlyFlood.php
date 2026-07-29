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
 * Provides a ShURLy destination plugin for Flood.
 */
#[MigrateDestination(
  id: 'shurly_flood'
)]
class OsuMigrationsShurlyFlood extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->database = $database;
    $this->supportsRollback = TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): OsuMigrationsShurlyFlood|static {
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
    return ['fid' => ['type' => 'integer']];
  }

  /**
   * {@inheritDoc}
   */
  public function import(Row $row, array $old_destination_id_values = []): bool|array {
    $record = [];
    $record['fid'] = $row->getSourceProperty('fid');
    $record['event'] = $row->getSourceProperty('event');
    $record['identifier'] = $row->getSourceProperty('identifier');
    $record['timestamp'] = $row->getSourceProperty('timestamp');
    $record['expiration'] = $row->getSourceProperty('expiration');
    return [
      $this->database->insert('shurly_flood')
        ->fields($record)
        ->execute(),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [
      'fid' => $this->t('The flood ID.'),
      'event' => $this->t('Name of the event.'),
      'identifier' => $this->t('Identifier of the visitor.'),
      'timestamp' => $this->t('The timestamp of the event.'),
      'expiration' => $this->t('The expiration timestamp of the event.'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function rollback(array $destination_identifier): void {
    $this->database->delete('shurly_flood')
      ->condition('fid', $destination_identifier['fid'])
      ->execute();
  }

}
