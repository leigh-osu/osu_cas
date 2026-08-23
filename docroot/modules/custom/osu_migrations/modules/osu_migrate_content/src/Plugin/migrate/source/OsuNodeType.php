<?php

namespace Drupal\osu_migrate_content\Plugin\migrate\source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Annotation\MigrateSource;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Plugin\migrate\source\d7\NodeType;

/**
 * The OSU Node Type source migration.
 *
 * @MigrateSource(
 *   id = "osu_d7_node_type",
 *   source_module = "node"
 * )
 */
class OsuNodeType extends NodeType {

  /**
   * Node Bundles to exclude from migration.
   *
   * @var array
   */
  protected array $excludeBundle;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, EntityTypeManagerInterface $entity_type_manager) {
    if (empty($configuration['exclude_bundle'])) {
      throw new \InvalidArgumentException('osu_d7_node_type missing exclude_bundle property.');
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entity_type_manager);
    $this->excludeBundle = (array) $configuration['exclude_bundle'];
  }

  /**
   * {@inheritDoc}
   */
  public function query() {
    $query = parent::query();
    if (!empty($this->excludeBundle)) {
      $query->condition('t.type', $this->excludeBundle, 'NOT IN');
    }
    return $query;
  }

}
