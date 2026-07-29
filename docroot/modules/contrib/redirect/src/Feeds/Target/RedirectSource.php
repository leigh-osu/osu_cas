<?php

namespace Drupal\redirect\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a redirect source field mapper.
 *
 * @FeedsTarget(
 *   id = "redirect_source",
 *   field_types = {
 *     "redirect_source"
 *   }
 * )
 */
class RedirectSource extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('path')
      ->addProperty('query');
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    $values['path'] = trim($values['path']);
  }

}
