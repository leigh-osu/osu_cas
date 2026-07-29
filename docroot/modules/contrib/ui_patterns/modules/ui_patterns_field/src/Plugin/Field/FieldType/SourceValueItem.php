<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_field\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Field Type to store UI Patterns source configuration.
 *
 * @property string $source_id
 * @property string $source
 */
#[FieldType(
  id: 'ui_patterns_source',
  label: new TranslatableMarkup('Source (UI Patterns)'),
  description: new TranslatableMarkup('Store an UI Patterns source configuration'),
  default_widget: 'ui_patterns_source',
  default_formatter: 'ui_patterns_source',
  list_class: FieldItemList::class,
)]
class SourceValueItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'source_id';
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    if (array_key_exists('source_id', $this->values)) {
      return empty($this->values['source_id']);
    }
    if (isset($this->properties['source_id'])) {
      return empty($this->properties['source_id']->getValue());
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(mixed $value, mixed $notify = TRUE): void {
    if (empty($value['source_id'])) {
      // It seems DataDefinition::setRequired() was not enough to make this
      // property mandatory.
      // @todo Can we do better/cleaner?
      return;
    }

    parent::setValue($value, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $definitions = [];
    $definitions['node_id'] = DataDefinition::create('string');
    $definitions['source_id'] = DataDefinition::create('string');
    $definitions['source'] = MapDataDefinition::create();
    $definitions['third_party_settings'] = MapDataDefinition::create();

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'node_id' => [
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
        'source_id' => [
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
        'source' => [
          'type' => 'blob',
          'size' => 'big',
          'serialize' => TRUE,
        ],
        'third_party_settings' => [
          'type' => 'blob',
          'size' => 'big',
          'serialize' => TRUE,
        ],
      ],
    ];
  }

}
