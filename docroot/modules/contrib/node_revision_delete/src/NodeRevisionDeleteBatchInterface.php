<?php

namespace Drupal\node_revision_delete;

use Drupal\node\NodeTypeInterface;

/**
 * An interface for batch controller to process node revision deletes.
 *
 * @package Drupal\node_revision_delete
 */
interface NodeRevisionDeleteBatchInterface {

  /**
   * Prepares and executes the plugin revision deletion batch.
   *
   * @param \Drupal\node\NodeTypeInterface[] $node_types
   *   The node types to process.
   */
  public function queueBatch(array $node_types = []): void;

  /**
   * Batch step definition to process the plugin revision deletion queue.
   *
   * Based on \Drupal\Core\Cron::processQueues().
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type.
   * @param array $context
   *   An associative array.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function queue(NodeTypeInterface $node_type, &$context): void;

  /**
   * Callback when finishing a plugin revision deletion batch job.
   *
   * @param bool $success
   *   Indicate that the batch API tasks were all completed successfully.
   * @param array $results
   *   The value set in $context['results'] by callback_batch_operation().
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public function finishQueue(bool $success, array $results, array $operations): void;

  /**
   * Prepares and executes the previous revision deletion batch.
   *
   * @param int $nid
   *   The node id.
   * @param int $currently_deleted_revision_id
   *   The current revision.
   * @param string|null $langcode
   *   (optional) The language code to filter revisions by. Defaults to the
   *   current language if not specified.
   *
   * @return bool
   *   TRUE if the batch has been set, FALSE if there are no revisions to delete
   *   and the batch is not set.
   */
  public function previousRevisionDeletionBatch(int $nid, int $currently_deleted_revision_id, ?string $langcode = NULL): bool;

  /**
   * Batch step definition to previous revisions.
   *
   * @param int $nid
   *   The node id.
   * @param int $original_revision_id
   *   The original revision the batch starts with.
   * @param string $langcode
   *   The language code to filter revisions by.
   * @param array $context
   *   The context of the current batch.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function deletePreviousRevision(int $nid, int $original_revision_id, string $langcode, array &$context): void;

  /**
   * Callback when finishing the batch of previous revisions.
   *
   * @param bool $success
   *   The flag to identify if batch has successfully run or not.
   * @param array $results
   *   The results from running context.
   * @param array $operations
   *   The array of operations remained unprocessed.
   */
  public function finishPreviousRevisions(bool $success, array $results, array $operations): void;

}
