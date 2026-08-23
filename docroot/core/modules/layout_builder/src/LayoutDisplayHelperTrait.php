<?php

namespace Drupal\layout_builder;

use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Methods to help with Layout Builder displays.
 */
trait LayoutDisplayHelperTrait {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Formats the query for checking if this display has overridden entities.
   *
   * @param \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $display
   *   The entity display.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query object that retrieves an entity's layout overrides.
   */
  protected function getOverrideQuery(LayoutEntityDisplayInterface $display) {
    $entity_type = $this->entityTypeManager->getDefinition($display->getTargetEntityTypeId());
    $query = $this->entityTypeManager->getStorage($display->getTargetEntityTypeId())->getQuery()
      ->accessCheck(FALSE)
      ->exists(OverridesSectionStorage::FIELD_NAME);
    if ($bundle_key = $entity_type->getKey('bundle')) {
      $query->condition($bundle_key, $display->getTargetBundle());
    }
    return $query;
  }

}
