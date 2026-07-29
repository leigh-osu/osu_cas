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
  id: 'shurly'
)]
class OsuMigrationsShurly extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  /**
   * Constructs a ShURLy Destination row.
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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): OsuMigrationsShurly|static {
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
  public function getIds() {
    return ['rid' => ['type' => 'integer']];
  }

  /**
   * {@inheritDoc}
   */
  public function import(Row $row, array $old_destination_id_values = []): array|bool {
    // Implement your custom import logic here.
    // This method should return an array of destination IDs if successful,
    // false on failure.
    $record = [];
    $record['rid'] = $row->getSourceProperty('rid');
    $record['destination'] = $row->getSourceProperty('destination');
    $record['hash'] = $row->getSourceProperty('hash');
    $record['custom'] = $row->getSourceProperty('custom');
    $record['created'] = $row->getSourceProperty('created');
    $record['source'] = $row->getSourceProperty('source');
    $record['uid'] = $row->getSourceProperty('uid');
    $record['count'] = $row->getSourceProperty('count');
    $record['last_used'] = $row->getSourceProperty('last_used');
    $record['active'] = $row->getSourceProperty('active');

    return [$this->database->insert('shurly')->fields($record)->execute()];
  }

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [
      'destination' => $this->t('The destination URL'),
      'hash' => $this->t('The hash of the ShURLy redirection.'),
      'custom' => $this->t('Boolean to represent if the link was custom.'),
      'created' => $this->t('timestamp the redirect was created.'),
      'source' => $this->t('The source URL.'),
      'uid' => $this->t('The uid of the user who created the ShURLy redirection.'),
      'count' => $this->t('The number of clicks.'),
      'last_used' => $this->t('Timestamp the last time the link was used.'),
      'active' => $this->t('Boolean represents status of the link.'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function rollback(array $destination_identifier) {
    $this->database->delete('shurly')
      ->condition('rid', $destination_identifier['rid'])
      ->execute();
  }

}
