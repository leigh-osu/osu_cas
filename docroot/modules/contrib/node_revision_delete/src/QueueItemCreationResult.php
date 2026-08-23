<?php

namespace Drupal\node_revision_delete;

/**
 * Result of attempting to create a queue item for a node.
 */
enum QueueItemCreationResult {

  case Created;
  case Deferred;
  case AlreadyExists;
  case Failed;

  /**
   * Whether the result indicates the item was or will be queued.
   */
  public function queued(): bool {
    return $this === self::Created || $this === self::Deferred;
  }

}
