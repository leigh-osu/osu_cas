<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\SourceTree;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Wrapper class for managing the UI Patterns source tree.
 */
class SourceTree {

  /**
   * The traverser.
   */
  protected Traverser $traverser;

  /**
   * The typed config manager.
   */
  protected TypedConfigManagerInterface $typedConfigManager;

  /**
   * The typed data instance.
   */
  protected ?TypedDataInterface $typedData = NULL;

  /**
   * Constructs a SourceTree instance.
   *
   * @param array $tree
   *   The source tree data.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface|null $typedConfigManager
   *   The typed config manager.
   */
  public function __construct(
    protected array $tree,
    ?TypedConfigManagerInterface $typedConfigManager = NULL,
  ) {
    if ($typedConfigManager === NULL) {
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      $typedConfigManager = \Drupal::service('config.typed');
    }
    $this->typedConfigManager = $typedConfigManager;
    $this->traverser = new Traverser();
  }

  /**
   * Returns the source tree data.
   *
   * @return array
   *   The source tree data.
   */
  public function toArray(): array {
    return $this->tree;
  }

  /**
   * Extracts translations from the source tree.
   *
   * @return array
   *   An array of translations.
   */
  public function getTranslations(): array {
    $translation_extractor = new TranslationExtractor();
    $this->traverse([$translation_extractor]);
    return $translation_extractor->getTranslations();
  }

  /**
   * Applies translations to the source tree.
   *
   * @param array $translations
   *   The translations to apply.
   */
  public function applyTranslations(array $translations): void {
    if (empty($translations)) {
      return;
    }

    $translation_applier = new TranslationApplier($translations);
    $this->traverse([$translation_applier]);
    // Invalidate cached typed data as tree has been modified.
    $this->typedData = NULL;
  }

  /**
   * Assigns missing node_ids throughout the tree.
   *
   * Every traversal assigns a node_id to source nodes lacking one, but only
   * in memory. Callers persisting the tree run this before storage so the
   * generated ids are saved and stay stable across loads.
   */
  public function ensureNodeIds(): void {
    $this->traverse([]);
  }

  /**
   * Returns a signature of the tree structure, ignoring translatable leaves.
   *
   * Two trees share a signature when they differ only in translatable leaf
   * values; any structural mutation (add / remove / move / reorder) changes
   * the signature.
   *
   * @return string
   *   The structure signature hash.
   */
  public function getStructureSignature(): string {
    $extractor = new TranslationExtractor();
    $this->traverse([$extractor]);
    $blanked = array_fill_keys(array_keys($extractor->getTranslations()), '');
    $copy = new SourceTree($this->tree, $this->typedConfigManager);
    if ($blanked !== []) {
      $copy->applyTranslations($blanked);
    }
    $canonical = $copy->toArray();
    self::stripNodeIds($canonical);
    self::ksortRecursive($canonical);
    return hash('xxh64', serialize($canonical));
  }

  /**
   * Recursively removes node_id entries from an array.
   *
   * Node_ids are node identity, not structure. Trees stored before node_ids
   * existed get fresh random ids on every load, so keeping them in the
   * canonical serialization would prevent two otherwise identical trees from
   * ever sharing a signature.
   *
   * @param array $array
   *   The array to clean in place.
   */
  private static function stripNodeIds(array &$array): void {
    unset($array['node_id']);
    foreach ($array as &$value) {
      if (is_array($value)) {
        self::stripNodeIds($value);
      }
    }
  }

  /**
   * Recursively sorts an array by key.
   *
   * Used to produce a canonical serialization that is insensitive to the order
   * in which associative-array keys were inserted (e.g. fixture order vs.
   * DB-column order after a round-trip through storage).
   *
   * @param array $array
   *   The array to sort in place.
   */
  private static function ksortRecursive(array &$array): void {
    ksort($array);
    foreach ($array as &$value) {
      if (is_array($value)) {
        self::ksortRecursive($value);
      }
    }
  }

  /**
   * Gets the typed data instance, lazy loading if necessary.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The typed data instance.
   */
  protected function getTypedData(): TypedDataInterface {
    if ($this->typedData === NULL) {
      $this->typedData = $this->typedConfigManager->createFromNameAndData(
        'ui_patterns_slot_source',
        $this->tree
      );
    }
    return $this->typedData;
  }

  /**
   * Traverses the source tree with the given processors.
   *
   * @param array $processors
   *   An array of ProcessorInterface instances.
   */
  protected function traverse(array $processors): void {
    // Typed data (lazily built and cached) drives the walk; the tree must
    // match the 'ui_patterns_slot_source' config schema.
    $typed_data = $this->getTypedData();
    $this->traverser->traverse($typed_data, $processors, $this->tree, []);
  }

}
