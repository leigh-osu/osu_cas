<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_field\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\ui_patterns\SourceTree\SourceTree;
use Drupal\ui_patterns_field\Field\SourceValueList;

/**
 * Field Type to store UI Patterns source configuration.
 *
 * @property string $source_id
 * @property array|string|null $source
 */
#[FieldType(
  id: 'ui_patterns_source',
  label: new TranslatableMarkup('Source (UI Patterns)'),
  description: new TranslatableMarkup('Store an UI Patterns source configuration'),
  default_widget: 'ui_patterns_source',
  default_formatter: 'ui_patterns_source',
  list_class: SourceValueList::class,
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
  public function setValue($values, $notify = TRUE): void {
    if (!isset($values)) {
      return;
    }
    $values = $this->normalizeToArray($values);
    if (empty($values['source_id'])) {
      // It seems DataDefinition::setRequired() was not enough to make this
      // property mandatory.
      // @todo Can we do better/cleaner?
      return;
    }
    if (is_array($values) && !empty($values['source'])) {
      $tree = new SourceTree($values);
      // Assign missing node_ids so they are persisted with the values —
      // signatures and translation maps rely on ids being stable across
      // entity loads.
      $tree->ensureNodeIds();
      $values = $tree->toArray();
    }
    $values['node_id'] = $values['node_id'] ?? '';
    // Ensure third_party_settings is an array.
    if (empty($values['third_party_settings']) || !is_array($values['third_party_settings'])) {
      $values['third_party_settings'] = [];
    }
    parent::setValue($values, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'synchronized_translation' => TRUE,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::fieldSettingsForm($form, $form_state);
    $form['synchronized_translation'] = [
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('Synchronized Translation'),
      '#description' => new TranslatableMarkup('Share the same structure across languages and translate only the text values.'),
      '#default_value' => $this->getSetting('synchronized_translation'),
      // Only meaningful when the field is translatable.
      '#states' => [
        'visible' => [
          ':input[name="translatable"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return $form;
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

  /**
   * Normalizes $values to an array.
   *
   * @param mixed $values
   *   The raw value.
   *
   * @return mixed
   *   The normalized value.
   */
  private function normalizeToArray(mixed $values): mixed {
    if ($values instanceof FieldItemInterface) {
      return $values->getValue();
    }
    if (is_string($values)) {
      return unserialize($values, ['allowed_classes' => FALSE]);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultValueWidget(FormStateInterface $form_state): mixed {
    return NULL;
  }

}
