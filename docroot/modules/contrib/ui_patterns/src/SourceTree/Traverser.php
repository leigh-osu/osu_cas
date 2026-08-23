<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\SourceTree;

use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Generic traverser for TypedData structures.
 *
 * Walks through nested TypedData elements and invokes processors,
 * allowing external logic to process elements at any level.
 */
class Traverser {

  /**
   * Traverse a TypedData element and invoke processors.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $element
   *   The typed data element to traverse.
   * @param array $processors
   *   Array of ProcessorInterface instances.
   * @param mixed $tree_item
   *   The source tree item (passed by reference).
   * @param array $parents
   *   The parent keys.
   * @param array $context
   *   The related source context.
   */
  public function traverse(TypedDataInterface $element, array $processors, mixed &$tree_item, array $parents = [], array $context = []): void {
    if ($tree_item === NULL) {
      return;
    }
    $context = $this->updateNodeContext($element, $tree_item, $parents, $context);
    $this->invokeProcessors($element, $processors, $tree_item, $parents, $context);
    $this->traverseChildren($element, $processors, $tree_item, $parents, $context);
  }

  /**
   * Updates the context with node_id information if applicable.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $element
   *   The typed data element.
   * @param mixed $tree_item
   *   The source tree item (passed by reference).
   * @param array $parents
   *   The parent keys.
   * @param array $context
   *   The current context.
   *
   * @return array
   *   The updated context.
   */
  private function updateNodeContext(TypedDataInterface $element, mixed &$tree_item, array $parents, array $context): array {
    $data_type = $element->getDataDefinition()->getDataType();
    if (is_array($tree_item) && in_array($data_type, ['ui_patterns_slot_source', 'ui_patterns_prop'], TRUE)) {
      if (empty($tree_item['node_id'])) {
        $tree_item['node_id'] = uniqid();
      }
      $context = ['source' => $tree_item, 'parents' => $parents];
    }
    return $context;
  }

  /**
   * Invokes all processors for the current element if context is ready.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $element
   *   The typed data element.
   * @param array $processors
   *   Array of ProcessorInterface instances.
   * @param mixed $tree_item
   *   The source tree item (passed by reference).
   * @param array $parents
   *   The parent keys.
   * @param array $context
   *   The current context.
   */
  private function invokeProcessors(TypedDataInterface $element, array $processors, mixed &$tree_item, array $parents, array $context): void {
    if (!isset($context['source']['node_id'])) {
      return;
    }
    $relative_path = array_slice($parents, count($context['parents']));
    $key_suffix = implode('.', $relative_path);
    $context['key'] = $context['source']['node_id'] . ($key_suffix ? ':' . $key_suffix : '');
    foreach ($processors as $processor) {
      $processor->process($element, $parents, $tree_item, $context);
    }
  }

  /**
   * Recurses into traversable children of the element.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $element
   *   The typed data element.
   * @param array $processors
   *   Array of ProcessorInterface instances.
   * @param mixed $tree_item
   *   The source tree item (passed by reference).
   * @param array $parents
   *   The parent keys.
   * @param array $context
   *   The current context.
   */
  private function traverseChildren(TypedDataInterface $element, array $processors, mixed &$tree_item, array $parents, array $context): void {
    if (!($element instanceof TraversableTypedDataInterface)) {
      return;
    }
    foreach ($element as $key => $child_element) {
      $child_parents = $parents;
      $child_parents[] = $key;
      if (is_array($tree_item) && array_key_exists($key, $tree_item)) {
        $this->traverse($child_element, $processors, $tree_item[$key], $child_parents, $context);
      }
      else {
        $null = NULL;
        $this->traverse($child_element, $processors, $null, $child_parents, $context);
      }
    }
  }

}
