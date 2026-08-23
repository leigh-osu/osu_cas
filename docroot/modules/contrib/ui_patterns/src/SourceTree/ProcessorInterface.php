<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\SourceTree;

use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Interface for source tree processors.
 *
 * Processors are invoked during TypedData traversal to process source nodes.
 *
 * @internal May be challenged.
 */
interface ProcessorInterface {

  /**
   * Process a TypedData element at a given path.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $element
   *   The typed data element.
   * @param array $parents
   *   The parent keys leading to this element.
   * @param mixed $tree_item
   *   The source tree item (may be modified by reference).
   * @param array $context
   *   Traversal context: 'source' (enclosing source node), 'parents' (its
   *   parent keys), 'key' (node_id-based translation key).
   */
  public function process(TypedDataInterface $element, array $parents, mixed &$tree_item, array $context): void;

}
