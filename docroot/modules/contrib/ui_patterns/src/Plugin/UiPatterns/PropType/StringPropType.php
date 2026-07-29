<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Plugin\UiPatterns\PropType;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\PropType;
use Drupal\ui_patterns\PropTypePluginBase;

/**
 * Provides a 'string' PropType.
 */
#[PropType(
  id: 'string',
  label: new TranslatableMarkup('String'),
  description: new TranslatableMarkup('Strings of text. May contain Unicode characters.'),
  default_source: 'textfield',
  convert_from: ['number', 'url', 'identifier'],
  schema: ['type' => 'string'],
  priority: 2,
  typed_data: ['string']
)]
class StringPropType extends PropTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public static function convertFrom(string $prop_type, mixed $value): mixed {
    return match ($prop_type) {
      'boolean' => (string) $value,
      'number' => (string) $value,
      'url' => $value,
      'identifier' => $value,
      'string' => $value,
      default => (string) $value,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(array $definition): array {
    $summary = parent::getSummary($definition);
    if (isset($definition['maxLength'])) {
      $summary[] = $this->t('Max length: @length', ['@length' => $definition['maxLength']]);
    }
    if (isset($definition['minLength'])) {
      $summary[] = $this->t('Min length: @length', ['@length' => $definition['minLength']]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function normalize(mixed $value, ?array $definition = NULL): mixed {
    $contentMediaType = $definition['contentMediaType'] ?? NULL;
    // A text/plain prop is plain text: strip every tag, even from Markup.
    if ($contentMediaType === 'text/plain') {
      return strip_tags(static::normalizer()->convertToString($value));
    }
    // Trust is decided by type. A MarkupInterface value (TranslatableMarkup,
    // Markup::create()) is already safe: keep it so Twig does not escape it.
    if ($value instanceof MarkupInterface) {
      return $value;
    }
    // A renderable or render array is rendered through Drupal's safe-HTML
    // pipeline; mark the result trusted for downstream code.
    if ($value instanceof RenderableInterface || (is_array($value) && Element::isRenderArray($value))) {
      return Markup::create(static::normalizer()->convertToString($value));
    }
    // A plain string is untrusted: leave it as-is so Twig autoescapes it
    // at render. To pass raw HTML, wrap the value in Markup::create().
    return static::normalizer()->convertToString($value);
  }

}
