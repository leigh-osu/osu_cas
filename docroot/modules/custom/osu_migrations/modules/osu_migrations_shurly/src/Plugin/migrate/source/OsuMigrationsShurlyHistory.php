<?php

namespace Drupal\osu_migrations_shurly\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Annotation\MigrateSource;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Migrate ShURLy history.
 *
 * @MigrateSource(
 *   id = "d7_shurly_history",
 *   source_module = "shurly"
 * )
 */
class OsuMigrationsShurlyHistory extends DrupalSqlBase {

  /**
   * {@inheritDoc}
   */
  public function getIds(): array {
    return [
      'hid' => [
        'type' => 'integer',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function query(): SelectInterface {
    $query = $this->select('shurly_history', 'shurly_history');
    $query->fields('shurly_history', [
      'hid',
      'rid',
      'vid',
      'source',
      'destination',
      'last_date',
      'count',
    ]);
    $query->distinct();
    return $query;
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

}
