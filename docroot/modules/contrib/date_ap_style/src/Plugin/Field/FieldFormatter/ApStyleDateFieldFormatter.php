<?php

declare(strict_types=1);

namespace Drupal\date_ap_style\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the AP style date field formatter.
 */
#[FieldFormatter(
  id: 'timestamp_ap_style',
  label: new TranslatableMarkup('AP Style'),
  field_types: [
    'datetime',
    'timestamp',
    'created',
    'changed',
    'published_at',
  ]
)]
class ApStyleDateFieldFormatter extends ApSelectFormatterBase {

  /**
   * {@inheritdoc}
   */
  protected function processItem($item, $options, $timezone, $langcode, $field_type): array {
    if ($field_type == 'datetime') {
      $timestamp = $item->date->getTimestamp();
    }
    else {
      $timestamp = $item->value;
    }

    return [
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
      '#markup' => $this->apStyleDateFormatter->formatTimestamp((int) $timestamp, $options, $timezone, $langcode),
    ];
  }

}
