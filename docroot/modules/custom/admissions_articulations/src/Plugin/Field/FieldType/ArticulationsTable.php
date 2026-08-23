<?php

namespace Drupal\admissions_articulations\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'admissions_articulations_link' field type.
 */
#[FieldType(
  id: "articulations_table",
  label: new TranslatableMarkup("Articulations Table"),
  category: new TranslatableMarkup("General"),
  default_widget: "string_textfield",
  default_formatter: "string",
)]
class ArticulationsTable extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Articulations URL'))
      ->setDescription(t('URL to articulations table.'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'value' => [
        'type' => 'varchar',
        'not null' => FALSE,
        'description' => 'URL of a articulations table.',
        'length' => 2048,
      ],
    ];

    $schema = [
      'columns' => $columns,
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

}
