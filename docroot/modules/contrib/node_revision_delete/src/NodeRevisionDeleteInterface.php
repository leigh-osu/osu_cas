<?php

namespace Drupal\node_revision_delete;

use Drupal\node\NodeInterface;

/**
 * The Node Revision Delete Interface.
 *
 * @package Drupal\node_revision_delete
 */
interface NodeRevisionDeleteInterface {

  /**
   * The table name for the queue semaphore table.
   *
   * This starts with 'queue_' to avoid confusion with 'node_revision_' tables.
   */
  const QUEUE_SEMAPHORE_TABLE = 'queue_node_revision_delete';

  /**
   * Get all revision IDs that are older than the specified revision.
   *
   * The revisions should have the same language as the specified language code.
   * If no language code is specified, the current language is used.
   *
   * @param int $nid
   *   The node id.
   * @param int $currently_deleted_revision_id
   *   The current revision.
   * @param string|null $langcode
   *   (optional) The language code to filter revisions by. Defaults to the
   *   current language if not specified.
   *
   * @return int[]
   *   An array of previous revision IDs, sorted by VID descending.
   */
  public function getPreviousRevisionIds(int $nid, int $currently_deleted_revision_id, ?string $langcode = NULL): array;

  /**
   * Get all revisions that are older than current deleted revision.
   *
   * The revisions should have the same language as the specified language code.
   * If no language code is specified, the current language is used.
   *
   * @param int $nid
   *   The node id.
   * @param int $currently_deleted_revision_id
   *   The current revision.
   * @param string|null $langcode
   *   (optional) The language code to filter revisions by. Defaults to the
   *   current language if not specified.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface[]
   *   An array with the previous revisions.
   *
   * @deprecated in node_revision_delete:2.1.0 and is removed from
   *   node_revision_delete:3.0.0. Use getPreviousRevisionIds() instead.
   *
   * @see https://www.drupal.org/node/3584874
   */
  public function getPreviousRevisions(int $nid, int $currently_deleted_revision_id, ?string $langcode = NULL): array;

  /**
   * Check if a node exists in the queue.
   *
   * @param int $nid
   *   The node id.
   *
   * @return bool
   *   TRUE if the node is in the queue, FALSE otherwise.
   */
  public function nodeExistsInQueue(int $nid): bool;

  /**
   * Create a queue item for a node if it is not already queued.
   *
   * @param int $nid
   *   The node id.
   *
   * @return \Drupal\node_revision_delete\QueueItemCreationResult
   *   The result of the queue item creation attempt.
   */
  public function createQueueItem(int $nid): QueueItemCreationResult;

  /**
   * Remove a node from the queue map.
   *
   * Called after queue processing to clean up the map entry.
   *
   * @param int $nid
   *   The node id.
   */
  public function removeNodeFromQueueMap(int $nid): void;

  /**
   * Check if a content type has at least one plugin enabled.
   *
   * @param string $content_type_id
   *   The content type ID.
   *
   * @return bool
   *   TRUE if the content type has at least one plugin enabled.
   */
  public function contentTypeHasEnabledPlugins(string $content_type_id): bool;

  /**
   * Gets the active revision IDs per language.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get the active revision IDs for.
   *
   * @return array<string, int>
   *   An array mapping language codes to active revision IDs.
   */
  public function getActiveVidsPerLanguage(NodeInterface $node): array;

}
