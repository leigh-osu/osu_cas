<?php

namespace Drupal\node_revision_delete\Plugin;

use Drupal\node\NodeInterface;

/**
 * Defines an interface for node revision delete plugins that are time-based.
 *
 * Time-based plugins need to be able to requeue nodes if they have revisions
 * that will potentially need to be deleted in the future.
 */
interface NodeRevisionDeleteTimeBasedInterface extends NodeRevisionDeleteInterface {

  /**
   * Gets the delay in seconds before the node should be requeued.
   *
   * The delay is calculated based on how long until the given revision will
   * become eligible for deletion.
   *
   * @param \Drupal\node\NodeInterface $revision
   *   The most recent revision that this plugin is protecting due to time
   *   constraints and that is not also protected by a non-time-based plugin.
   *   The revision will be in the correct translation.
   *
   * @return int
   *   The number of seconds to delay before requeuing. A value of 0 means the
   *   revision is ready to be processed immediately.
   */
  public function getDelay(NodeInterface $revision): int;

}
