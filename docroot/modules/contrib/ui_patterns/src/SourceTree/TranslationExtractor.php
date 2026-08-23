<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\SourceTree;

use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Processor to extract translations from source nodes.
 *
 * @internal May be challenged.
 */
final class TranslationExtractor implements ProcessorInterface {

  /**
   * Collected translations.
   */
  private array $translations = [];

  /**
   * Returns the translations.
   */
  public function getTranslations(): array {
    return $this->translations;
  }

  /**
   * {@inheritdoc}
   */
  public function process(TypedDataInterface $element, array $parents, mixed &$tree_item, array $context): void {
    $definition = $element->getDataDefinition();

    // Honour the 'translatable' flag when present.
    if ($definition instanceof \ArrayAccess && $definition->offsetExists('translatable') && $definition->offsetGet('translatable')) {
      $this->translations[$context['key']] = $tree_item;
    }
    // Fallback: 'label' and 'text' types are translatable even when the schema
    // omits the flag.
    elseif (in_array($definition->getDataType(), ['label', 'text'], TRUE)) {
      $this->translations[$context['key']] = $tree_item;
    }
  }

}
