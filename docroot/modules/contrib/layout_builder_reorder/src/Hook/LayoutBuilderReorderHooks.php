<?php

declare(strict_types=1);

namespace Drupal\layout_builder_reorder\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for layout_builder_reorder.
 */
class LayoutBuilderReorderHooks {

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$types): void {
    if (isset($types['layout_builder'])) {
      $types['layout_builder']['#pre_render'][] = '\Drupal\layout_builder_reorder\SectionRearrangeRender::preRender';
    }
  }

}
