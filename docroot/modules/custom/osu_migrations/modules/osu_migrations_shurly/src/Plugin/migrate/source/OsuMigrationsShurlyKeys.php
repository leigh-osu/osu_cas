<?php

namespace Drupal\osu_migrations_shurly\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Annotation\MigrateSource;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Migrate ShURLy API keys.
 *
 * @MigrateSource(
 *   id = "d7_shurly_keys",
 *   source_module = "shurly_service"
 * )
 */
class OsuMigrationsShurlyKeys extends DrupalSqlBase {

  /**
   * {@inheritDoc}
   */
  public function getIds(): array {
    return [
      'apikey' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function query(): SelectInterface {
    $query = $this->select('shurly_keys', 'shurly_keys');
    $query->fields('shurly_keys', [
      'uid',
      'apikey',
    ]);
    $query->distinct();
    return $query;
  }

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [
      'uid' => $this->t('The user ID.'),
      'apikey' => $this->t('The API Key.'),

    ];
  }

}
