<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_field\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\ui_patterns\SourceTree\SourceTree;
use Drupal\ui_patterns_field\Plugin\Field\FieldType\SourceValueItem;

/**
 * Field item list of the ui_patterns_source field type.
 *
 * Implements the synchronized translation behavior: merged reads and
 * structure cascade between a translation and the default language.
 */
class SourceValueList extends FieldItemList {

  /**
   * Memoized field-wide translations map.
   */
  protected array $translationsMap = [];

  /**
   * Fingerprint of the raw values the memoized map was built from.
   */
  protected ?string $translationsMapFingerprint = NULL;

  /**
   * {@inheritdoc}
   */
  protected function defaultValueWidget(FormStateInterface $form_state) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Read rule for synchronized non-default translations: the default
   * language's deltas define the structure, with this translation's leaves
   * applied on top. Raw stored deltas remain available via getRawValues().
   */
  public function getValue() {
    if (!$this->isSynchronizedTranslation()) {
      return parent::getValue();
    }
    $entity = $this->getEntity();
    \assert($entity instanceof TranslatableInterface);
    // The default list is never synchronized-merging, so its getValue() is
    // the raw stored state.
    $default_values = $entity->getUntranslated()->get($this->getName())->getValue();
    $map = $this->buildTranslationsMap();
    $values = [];
    foreach ($default_values as $delta => $default_item_values) {
      if ($map === [] || !\is_array($default_item_values) || empty($default_item_values['source'])) {
        $values[$delta] = $default_item_values;
        continue;
      }
      $tree = new SourceTree($default_item_values);
      $tree->applyTranslations($map);
      $values[$delta] = $tree->toArray();
    }
    return $values;
  }

  /**
   * Returns the raw stored deltas of THIS translation, without the merge.
   *
   * @return array
   *   Delta-indexed raw item values as stored for this translation row.
   */
  public function getRawValues(): array {
    return parent::getValue();
  }

  /**
   * Whether this list belongs to a synchronized non-default translation.
   *
   * @return bool
   *   TRUE when the entity is a non-default translation with synchronized
   *   translation enabled.
   */
  private function isSynchronizedTranslation(): bool {
    $entity = $this->getEntity();
    return $entity instanceof TranslatableInterface
      && !$entity->isDefaultTranslation()
      && (bool) $this->getSetting('synchronized_translation');
  }

  /**
   * {@inheritdoc}
   *
   * Write-side translation sync: a synchronized non-default translation
   * whose structure diverges from the default cascades its structure to the
   * default language here — once per field per save.
   */
  public function preSave(): void {
    parent::preSave();
    if (!$this->isSynchronizedTranslation()) {
      return;
    }
    $this->cascadeStructureToDefault();
  }

  /**
   * Cascades structural translation edits to the default language.
   *
   * The default tree is the structural authority: a node added, removed or
   * moved on a translation is only visible once the default adopts the new
   * structure. The default keeps its own leaf values wherever the node_ids
   * still exist.
   *
   * Guarded by the entity's original state: when this translation is
   * structurally unchanged, the mismatch comes from a default-side edit and
   * must not be cascaded back.
   */
  private function cascadeStructureToDefault(): void {
    $own_values = $this->getRawValues();
    // An empty translation row is "no translation yet" — never wipe the
    // default from it.
    if ($own_values === []) {
      return;
    }
    $entity = $this->getEntity();
    \assert($entity instanceof TranslatableInterface);
    $default_list = $entity->getUntranslated()->get($this->getName());
    \assert($default_list instanceof SourceValueList);

    $own_signatures = $this->structureSignatures($own_values);
    if ($own_signatures === $this->structureSignatures($default_list->getValue())) {
      // Leaf-only edits: stored as entered, applied by the read merge.
      return;
    }
    if ($this->isStructurallyUnchangedSinceLoad($own_signatures)) {
      // The divergence comes from a default-side structural edit.
      return;
    }

    // Rebuild the default: this translation's structure with the default
    // language's own leaves applied back.
    $default_map = $default_list->buildTranslationsMap();
    $new_default_values = [];
    foreach ($own_values as $delta => $item_values) {
      if (!\is_array($item_values) || empty($item_values['source'])) {
        $new_default_values[$delta] = $item_values;
        continue;
      }
      $tree = new SourceTree($item_values);
      if ($default_map !== []) {
        $tree->applyTranslations($default_map);
      }
      $new_default_values[$delta] = $tree->toArray();
    }
    $default_list->setValue($new_default_values);
  }

  /**
   * Whether this translation's structure matches its loaded DB state.
   *
   * @param array $own_signatures
   *   The delta-indexed structure signatures of the current values.
   *
   * @return bool
   *   TRUE when the original entity holds this translation with the same
   *   structure signatures.
   */
  private function isStructurallyUnchangedSinceLoad(array $own_signatures): bool {
    $entity = $this->getEntity();
    \assert($entity instanceof TranslatableInterface);
    $original_entity = $entity->getOriginal();
    if (!$original_entity instanceof TranslatableInterface
      || !$original_entity->hasTranslation($entity->language()->getId())) {
      return FALSE;
    }
    $original_list = $original_entity
      ->getTranslation($entity->language()->getId())
      ->get($this->getName());
    if (!$original_list instanceof SourceValueList) {
      return FALSE;
    }
    return $own_signatures === $this->structureSignatures($original_list->getRawValues());
  }

  /**
   * Returns per-delta structure signatures for a set of field values.
   *
   * Translatable leaf values are blanked by the signature, so two value
   * sets share signatures exactly when they are structurally identical.
   *
   * @param array $values
   *   Delta-indexed field item values.
   *
   * @return array
   *   Delta-indexed structure signature hashes (deltas without a source
   *   tree are skipped).
   */
  private function structureSignatures(array $values): array {
    $signatures = [];
    foreach ($values as $delta => $item_values) {
      if (\is_array($item_values) && !empty($item_values['source'])) {
        $signatures[$delta] = (new SourceTree($item_values))->getStructureSignature();
      }
    }
    return $signatures;
  }

  /**
   * Builds the field-wide translations map across all deltas.
   *
   * Extracts translatable leaves from every delta's raw source tree into a
   * single flat `node_id:path` keyed map, so a node moved to another delta
   * still finds its translated leaves.
   *
   * Memoized against a fingerprint of the raw values: any mutation triggers
   * a rebuild, no manual invalidation needed.
   *
   * @return array
   *   Flat `node_id:path` keyed translations map.
   */
  public function buildTranslationsMap(): array {
    $raw_values = [];
    foreach ($this->list as $delta => $item) {
      \assert($item instanceof SourceValueItem);
      // Item getValue() is raw by definition (raw = item, merged = list).
      $raw_values[$delta] = $item->getValue();
    }
    $fingerprint = hash('xxh64', serialize($raw_values));
    if ($fingerprint === $this->translationsMapFingerprint) {
      return $this->translationsMap;
    }
    $map = [];
    foreach ($raw_values as $values) {
      if (empty($values['source']) || !\is_array($values['source'])) {
        continue;
      }
      $map += (new SourceTree($values))->getTranslations();
    }
    $this->translationsMap = $map;
    $this->translationsMapFingerprint = $fingerprint;
    return $map;
  }

  /**
   * Returns raw source values for every delta, without merge logic.
   *
   * @return array
   *   Delta-indexed list of ['source' => <raw>] entries.
   */
  public function getRawSources(): array {
    $result = [];
    foreach ($this->list as $delta => $item) {
      \assert($item instanceof SourceValueItem);
      $raw = $item->get('source')->getValue();
      $result[$delta] = ['source' => \is_array($raw) ? $raw : []];
    }
    return $result;
  }

  /**
   * Returns the field-wide translations map for non-default translations.
   *
   * Returns [] on the default translation, so consumers can use the result
   * directly as a language-override payload.
   *
   * @return array
   *   Flat `node_id:path` keyed translations map.
   */
  public function getTranslations(): array {
    $entity = $this->getEntity();
    if (!$entity instanceof TranslatableInterface || $entity->isDefaultTranslation()) {
      return [];
    }
    return $this->buildTranslationsMap();
  }

}
