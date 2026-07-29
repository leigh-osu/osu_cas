<?php

namespace Drupal\node_revision_delete\Plugin;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\node\NodeInterface;

/**
 * Defines an optimized interface for node revision delete plugins.
 *
 * Plugins implementing this interface use entity queries to determine which
 * revisions to delete or protect, rather than loading each revision entity
 * individually via checkRevisions().
 *
 * @see \Drupal\node_revision_delete\Plugin\NodeRevisionDeleteInterface
 */
interface NodeRevisionDeleteQueryInterface extends NodeRevisionDeleteInterface {

  /**
   * Returns revision IDs that should be deleted.
   *
   * Plugins should use entity queries to determine which revisions match their
   * deletion criteria, rather than loading revision entities.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity revision query set up to select for the correct node ID and
   *   language.
   * @param int $active_vid
   *   The active revision ID for this language.
   * @param \Drupal\node\NodeInterface $node
   *   The node entity in the default revision.
   *
   * @return int[]
   *   The revision IDs to delete.
   */
  public function getRevisionsToDelete(QueryInterface $query, int $active_vid, NodeInterface $node): array;

  /**
   * Returns revision IDs that must be protected from deletion.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity revision query set up to select for the correct node ID and
   *   language.
   * @param int $active_vid
   *   The active revision ID for this language.
   * @param \Drupal\node\NodeInterface $node
   *   The node entity in the default revision.
   *
   * @return int[]
   *   The revision IDs to protect.
   */
  public function getRevisionsToProtect(QueryInterface $query, int $active_vid, NodeInterface $node): array;

}
