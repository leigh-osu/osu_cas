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
  id: 'shurly_keys'
)]
class OsuMigrationsShurlyKeys extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  /**
   * Construct a ShURLy API Keys Destination row.
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
    return [
      'apikey' => [
        'type' => 'string',
        'length' => 35,
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function import(Row $row, array $old_destination_id_values = []): bool|array {
    $record = [];
    $record['uid'] = $row->getSourceProperty('uid');
    $record['apikey'] = $row->getSourceProperty('apikey');
    return [
      $this->database->insert('shurly_keys')
        ->fields($record)
        ->execute(),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [
      'uid' => $this->t('The User ID.'),
      'apikey' => $this->t('The API Key.'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function rollback(array $destination_identifier) {
    $this->database->delete('shurly_keys')
      ->condition('apikey', $destination_identifier['apikey'])
      ->execute();
  }

}
