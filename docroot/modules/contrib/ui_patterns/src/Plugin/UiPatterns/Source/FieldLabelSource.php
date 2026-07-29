<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourceTags;

/**
 * Plugin implementation of the field_label source.
 */
#[Source(
  id: 'field_label',
  label: new TranslatableMarkup('[Field] Label'),
  description: new TranslatableMarkup('Field label source plugin.'),
  prop_types: ['string'],
  tags: [SourceTags::Field->value],
  context_requirements: ['field_formatter'],
  context_definitions: [
    'entity' => new ContextDefinition('entity', label: new TranslatableMarkup('Entity'), required: TRUE),
    'field_name' => new ContextDefinition('string', label: new TranslatableMarkup('Field Name'), required: TRUE),
  ]
)]
class FieldLabelSource extends FieldSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $field_definition = $this->getFieldDefinition();
    if (!$field_definition) {
      return NULL;
    }
    // Return the raw label: the render pipeline normalizes it once.
    return $field_definition->getLabel();
  }

}
