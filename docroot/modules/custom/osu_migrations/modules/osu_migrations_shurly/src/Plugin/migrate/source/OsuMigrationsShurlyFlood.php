<?php

namespace Drupal\osu_migrations_shurly\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Annotation\MigrateSource;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Migrate ShURLy API keys.
 *
 * @MigrateSource(
 *   id = "d7_shurly_flood",
 *   source_module = "shurly"
 * )
 */
class OsuMigrationsShurlyFlood extends DrupalSqlBase {

  /**
   * {@inheritDoc}
   */
  public function getIds(): array {
    return [
      'fid' => [
        'type' => 'integer',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function query(): SelectInterface {
    $query = $this->select('shurly_flood', 'shurly_flood');
    $query->fields('shurly_flood', [
      'fid',
      'event',
      'identifier',
      'timestamp',
      'expiration',
    ]);
    $query->distinct();
    return $query;
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

}
