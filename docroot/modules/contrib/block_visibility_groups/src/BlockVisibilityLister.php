<?php

namespace Drupal\block_visibility_groups;

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Provides functionality to get block visibility conditions and labels.
 */
trait BlockVisibilityLister {

  /**
   * Get labels for groups.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   *
   * @return array
   *   The list of labels keyed by entity ID.
   */
  protected function getBlockVisibilityLabels(EntityStorageInterface $storage): array {
    $block_visibility_groups = $storage->loadMultiple();
    $labels = [];
    foreach ($block_visibility_groups as $type) {
      $labels[$type->id()] = $type->label();
    }
    return $labels;
  }

  /**
   * Get the visibility group for a block.
   *
   * @param \Drupal\block\Entity\Block $block
   *   The block instance.
   *
   * @return string
   *   The config group name, or empty string if not set.
   */
  protected function getGroupForBlock(Block $block): string {
    $conditions = $block->getVisibilityConditions();
    if ($conditions->has('condition_group')) {
      $condition_config = $conditions->get('condition_group')
        ->getConfiguration();
      return $condition_config['block_visibility_group'] ?? '';
    }
    return '';
  }

}
