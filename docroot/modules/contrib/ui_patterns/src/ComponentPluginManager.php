<?php

declare(strict_types=1);

namespace Drupal\ui_patterns;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\CategorizingPluginManagerTrait;
use Drupal\Core\Plugin\Component;
use Drupal\Core\Theme\Component\ComponentValidator;
use Drupal\Core\Theme\Component\SchemaCompatibilityChecker;
use Drupal\Core\Theme\ComponentNegotiator;
use Drupal\Core\Theme\ComponentPluginManager as SdcPluginManager;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\ui_patterns\SchemaManager\ReferencesResolver;

/**
 * UI Patterns extension of SDC component plugin manager.
 */
class ComponentPluginManager extends SdcPluginManager {

  // @todo Remove when Core 11.2.0 will be the minimum version supported.
  // As it will be managed by Core component plugin manager.
  use CategorizingPluginManagerTrait;

  /**
   * The decorated component plugin manager.
   */
  protected SdcPluginManager $decorated;

  /**
   * The prop type plugin manager.
   */
  protected PropTypePluginManager $propTypePluginManager;

  /**
   * The prop type adapter plugin manager.
   */
  protected PropTypeAdapterPluginManager $propTypeAdapterPluginManager;

  /**
   * The reference resolver.
   */
  protected ReferencesResolver $referencesSolver;

  // @phpstan-ignore pluginManagerSetsCacheBackend.missingCacheBackend
  public function __construct(
    ModuleHandlerInterface $moduleHandler,
    ThemeHandlerInterface $themeHandler,
    CacheBackendInterface $cacheBackend,
    ConfigFactoryInterface $configFactory,
    ThemeManagerInterface $themeManager,
    ComponentNegotiator $componentNegotiator,
    FileSystemInterface $fileSystem,
    SchemaCompatibilityChecker $compatibilityChecker,
    ComponentValidator $componentValidator,
    string $appRoot,
    SdcPluginManager $decorated,
  ) {
    // Hybrid class: it both EXTENDS the core component plugin manager
    // (parent::__construct below gives us our own discovery, cache and
    // factory) AND WRAPS the next layer of the decoration chain via
    // $this->decorated.
    //
    // `parent` and `$this->decorated` are NOT the same thing:
    //
    //  - `parent` always means core SDC code running on $this, using our own
    //    state.
    //
    //  - `$this->decorated` is wired at container build time. With canvas
    //    installed it is the canvas decorator; without canvas it is a
    //    SEPARATE core SDC instance (a different object than $this, with
    //    its own state). Chain order is set in ui_patterns.services.yml
    //    via `decoration_priority: -100`, which keeps us outermost. A
    //    typical chain is: ui_patterns → canvas → core SDC.
    //
    // When adding or changing methods:
    //
    //  - Methods that READ definitions — getDefinition, getAllComponents,
    //    find, createInstance, getInstance — must NOT delegate to
    //    $this->decorated. Our annotations and inlined $refs live in our
    //    own cache; reading through $this->decorated returns raw
    //    definitions and ComponentValidator rejects any component whose
    //    `type` comes from an inlined `$ref: ui-patterns://...`.
    //
    //  - Methods that WRITE or invalidate — processDefinition,
    //    clearCachedDefinitions — DO forward to $this->decorated, so the
    //    next layer of the chain (e.g. canvas) still gets a chance to run
    //    its own logic.
    $this->decorated = $decorated;
    parent::__construct(
      $moduleHandler,
      $themeHandler,
      $cacheBackend,
      $configFactory,
      $themeManager,
      $componentNegotiator,
      $fileSystem,
      $compatibilityChecker,
      $componentValidator,
      $appRoot
    );
    $this->alterInfo('component_info');
  }

  /**
   * Sets the prop type plugin manager.
   */
  public function setPropTypePluginManager(PropTypePluginManager $propTypePluginManager): void {
    $this->propTypePluginManager = $propTypePluginManager;
  }

  /**
   * Sets the prop type adapter plugin manager.
   */
  public function setPropTypePluginAdapter(PropTypeAdapterPluginManager $propTypeAdapterPluginManager): void {
    $this->propTypeAdapterPluginManager = $propTypeAdapterPluginManager;
  }

  /**
   * Sets reference resolver.
   */
  public function setReferenceSolver(ReferencesResolver $referencesSolver): void {
    $this->referencesSolver = $referencesSolver;
  }

  /**
   * Sets module extension list.
   */
  public function setModuleExtensionList(ModuleExtensionList $moduleExtensionList): void {
    $this->moduleExtensionList = $moduleExtensionList;
  }

  /**
   * Correct SDC Component definition.
   *
   * @param array $definition
   *   The definition to clean.
   */
  protected function cleanDefinition(array &$definition): void {
    // Name is mandatory, so this precaution should never happen. But we have
    // seen SDC without name property in the wild.
    if (!isset($definition['name'])) {
      $definition['name'] = explode(':', $definition['id'])[1];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = $this->getCachedDefinitions();
    if ($definitions) {
      return $definitions;
    }

    $definitions = $this->decorated->getCachedDefinitions();
    if ($definitions) {
      $this->setCachedDefinitions($definitions);
      return $definitions;
    }

    $definitions = parent::getDefinitions();
    $this->decorated->setCachedDefinitions($definitions);
    $this->setCachedDefinitions($definitions);

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions(): void {
    parent::clearCachedDefinitions();
    $this->decorated->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function useCaches($use_caches = FALSE): void {
    $this->decorated->useCaches($use_caches);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->decorated->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->decorated->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->decorated->getCacheMaxAge();
  }

  /**
   * {@inheritdoc}
   */
  public function setCacheBackend(CacheBackendInterface $cache_backend, $cache_key, array $cache_tags = []): void {
    parent::setCacheBackend($cache_backend, $cache_key . '.ui_patterns', $cache_tags);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function processDefinition(&$definition, $plugin_id): void {
    // Delegate to decorated service.
    $this->decorated->processDefinition($definition, $plugin_id);
    $this->cleanDefinition($definition);
    $this->processDefinitionCategory($definition);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  protected function processDefinitionCategory(&$definition): void {
    // 'label' and 'category' are expected by CategorizingPluginManagerTrait.
    $this->cleanDefinition($definition);
    $definition['label'] = $definition['name'];
    $definition['category'] = $definition['group'] ?? $this->t('Other');
  }

  /**
   * {@inheritdoc}
   */
  protected function alterDefinition(array $definition): array {
    // Overriding SDC alterDefinition method.
    $definition = parent::alterDefinition($definition);
    // Adding custom UI Patterns logic.
    $fallback_prop_type_id = $this->propTypePluginManager->getFallbackPluginId('');
    $definition = $this->alterLinks($definition);
    $definition = $this->alterSlots($definition);
    $definition = $this->annotateSlots($definition);
    return $this->annotateProps($definition, $fallback_prop_type_id);
  }

  /**
   * Alter links.
   */
  private function alterLinks(array $definition): array {
    if (!isset($definition['links'])) {
      return $definition;
    }
    // Resolve the short notation.
    foreach ($definition['links'] as $delta => $link) {
      if (is_array($link)) {
        continue;
      }
      $definition['links'][$delta] = [
        'url' => (string) $link,
      ];
    }
    return $definition;
  }

  /**
   * Alter slots.
   */
  private function alterSlots(array $definition): array {
    if (!isset($definition['slots'])) {
      return $definition;
    }
    // Prevent slots without title from breaking.
    foreach ($definition['slots'] as $slot_id => $slot) {
      $definition['slots'][$slot_id]['title'] = $slot['title'] ?? $slot_id;
    }
    return $definition;
  }

  /**
   * Annotate each slot in a component definition.
   */
  private function annotateSlots(array $definition): array {
    if (empty($definition['slots'])) {
      return $definition;
    }
    $slot_prop_type = $this->propTypePluginManager->createInstance('slot', []);
    foreach ($definition['slots'] as $slot_id => $slot) {
      $slot['ui_patterns']['type_definition'] = $slot_prop_type;
      $definition['slots'][$slot_id] = $slot;
    }
    return $definition;
  }

  /**
   * Annotate each prop in a component definition.
   *
   * This is the main purpose of overriding SDC component plugin manager.
   * We add a 'ui_patterns' object in each prop schema of the definition.
   */
  private function annotateProps(array $definition, string $fallback_prop_type_id): array {
    // In JSON schema, 'required' is out of the prop definition.
    if (isset($definition['props']['required'])) {
      foreach ($definition['props']['required'] as $prop_id) {
        $definition['props']['properties'][$prop_id]['ui_patterns']['required'] = TRUE;
      }
    }
    if (isset($definition['variants'])) {
      $definition['props']['properties']['variant'] = $this->buildVariantProp($definition);
    }
    $definition['props']['properties'] = $this->addAttributesProp($definition);
    foreach ($definition['props']['properties'] as $prop_id => $prop) {
      $definition['props']['properties'][$prop_id] = $this->annotateProp($prop_id, $prop, $fallback_prop_type_id);
    }
    return $definition;
  }

  /**
   * Annotate a single prop.
   */
  private function annotateProp(string $prop_id, array $prop, string $fallback_prop_type_id): array {
    $prop['title'] = $prop['title'] ?? $prop_id;
    $prop = $this->referencesSolver->resolve($prop);
    /** @var \Drupal\ui_patterns\PropTypeInterface $prop_type */
    $prop_type = $this->propTypePluginManager->guessFromSchema($prop);
    if ($prop_type->getPluginId() === $fallback_prop_type_id) {
      // Sometimes, a prop JSON schema is different enough to not be caught by
      // the compatibility checker, but close enough to address the same
      // sources as an existing prop type with only some small unidirectional
      // transformation of the data. So, we need an adapter plugin.
      $prop_type_adapter = $this->propTypeAdapterPluginManager->guessFromSchema($prop);
      if ($prop_type_adapter) {
        $prop_type_id = $prop_type_adapter->getPropTypeId();
        $prop_type = $this->propTypePluginManager->createInstance($prop_type_id);
        $prop['ui_patterns']['prop_type_adapter'] = $prop_type_adapter->getPluginId();
      }
    }
    $prop['ui_patterns']['type_definition'] = $prop_type;
    $prop['ui_patterns']['summary'] = ($prop_type instanceof PropTypeInterface) ? $prop_type->getSummary($prop) : '';
    return $prop;
  }

  /**
   * Add attributes prop.
   *
   * 'attribute' is one of the 2 'magic' props: its name and type are already
   * set. Always available because automatically added by
   * ComponentsTwigExtension::mergeAdditionalRenderContext().
   */
  private function addAttributesProp(array $definition): array {
    // Let's put it at the beginning (for forms).
    return array_merge(
      [
        'attributes' => [
          'title' => 'Attributes',
          '$ref' => 'ui-patterns://attributes',
        ],
      ],
      $definition['props']['properties'] ?? [],
    );
  }

  /**
   * Build variant prop.
   *
   * 'variant' is one of the 2 'magic' props: its name and type are already set.
   * Available if at least a variant is set in the component definition.
   */
  private function buildVariantProp(array $definition): array {
    $enums = [];
    $meta_enums = [];
    foreach ($definition['variants'] as $variant_id => $variant) {
      $enums[] = $variant_id;
      $meta_enums[$variant_id] = $variant['title'] ?? $variant_id;
    }
    return [
      'title' => 'Variant',
      '$ref' => 'ui-patterns://variant',
      'enum' => $enums,
      'meta:enum' => $meta_enums,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    // Currently not working to call the decorated service.
    $definitions = parent::findDefinitions();
    // Add annotated_name property to distinct components with the same name.
    $labels = array_column($definitions, 'name');
    $duplicate_labels = array_unique(array_intersect($labels, array_unique(array_diff_key($labels, array_unique($labels)))));
    foreach ($definitions as $id => $definition) {
      $definitions[$id]['annotated_name'] = $this->getAnnotatedLabel($definition, $duplicate_labels);
    }
    return $definitions;
  }

  /**
   * Add annotation to label when many components share the same name.
   */
  private function getAnnotatedLabel(array $definition, array $duplicate_labels): string {
    $label = $definition['name'] ?? $definition['machineName'];
    if (!in_array($label, $duplicate_labels, TRUE)) {
      return $label;
    }
    if (!isset($definition['provider'])) {
      return $label;
    }
    return $label . ' (' . $this->getExtensionLabel($definition['provider']) . ')';
  }

  /**
   * Get the extension (module or theme) label.
   */
  private function getExtensionLabel(string $extension): string {
    if ($this->moduleHandler->moduleExists($extension)) {
      return $this->moduleExtensionList->getName($extension);
    }
    if ($this->themeHandler->themeExists($extension)) {
      return $this->themeHandler->getTheme($extension)->info['name'];
    }
    return $extension;
  }

  /**
   * Calculate dependencies of a component.
   *
   * @param \Drupal\Core\Plugin\Component $component
   *   The component.
   *
   * @return array
   *   Config Dependencies.
   */
  public function calculateDependencies(Component $component): array {
    $definition = $component->getPluginDefinition();
    $provider = ($definition instanceof PluginDefinitionInterface) ? $definition->getProvider() : (string) ($definition['provider'] ?? '');
    $extension_type = $this->getExtensionType($provider);
    return (empty($provider) || empty($extension_type)) ? [] : [$extension_type => [$provider]];
  }

  /**
   * Returns the negotiated (replaces) definition.
   */
  public function negotiateDefinition(string $component_id): array {
    $negotiated_component_id = $this->componentNegotiator->negotiate($component_id, $this->getDefinitions());
    $found_id = $negotiated_component_id ?? $component_id;
    $original_definition = $this->getDefinition($component_id, FALSE);
    $definition = $this->getDefinition($found_id, FALSE);
    if ($found_id !== $component_id) {
      // We need to reset id machineName and provider to the original values
      // because the replaced component must behave like the original.
      $definition['replaced_by'] = $definition['id'];
      $definition['id'] = $component_id;
      unset($definition['replaces']);
      $definition['machineName'] = $original_definition['machineName'];
      $definition['provider'] = $original_definition['provider'];
      if (isset($original_definition['noUi'])) {
        $definition['noUi'] = $original_definition['noUi'];
      }
    }
    return $definition;
  }

  /**
   * Determine if a definition is hidden.
   *
   * @param array $definition
   *   The definition to check.
   * @param bool $include_replaces
   *   Whether to include definitions that replace others.
   *
   * @return bool
   *   TRUE if the definition is hidden, FALSE otherwise.
   */
  private function isHiddenDefinition(array $definition, bool $include_replaces = FALSE): bool {
    if (!empty($definition['replaces']) && $include_replaces === FALSE) {
      return TRUE;
    }
    return $definition['noUi'] ?? FALSE;
  }

  /**
   * Returns the negotiated sorted definitions.
   */
  public function getNegotiatedSortedDefinitions(?array $definitions = NULL, string $label_key = 'label', bool $include_replaces = FALSE): array {
    $definitions = $this->getSortedDefinitions($definitions, $label_key);
    $negotiated_definitions = [];
    foreach ($definitions as $id => $definition) {
      $negotiated_definition = $this->negotiateDefinition($id);

      if ($this->isHiddenDefinition($negotiated_definition, $include_replaces)) {
        continue;
      }
      $negotiated_definitions[$id] = $negotiated_definition;
      if (!empty($definition['replaces']) && $include_replaces === TRUE) {
        $suffix = ' (don\'t use. Use: ' . $definition['replaces'] . ')';
        if ($negotiated_definitions[$id]['annotated_name']) {
          $negotiated_definitions[$id]['annotated_name'] .= $suffix;
        }
      }
    }
    return $negotiated_definitions;
  }

  /**
   * Returns the negotiated sorted grouped definitions.
   */
  public function getNegotiatedGroupedDefinitions(?array $definitions = NULL, string $label_key = 'label', bool $include_replaces = FALSE): array {
    $definitions = $this->getNegotiatedSortedDefinitions($definitions ?? $this->getDefinitions(), $label_key, $include_replaces);
    $grouped_definitions = [];
    foreach ($definitions as $id => $definition) {
      $grouped_definitions[(string) $definition['category']][$id] = $definition;
    }
    return $grouped_definitions;
  }

  /**
   * Get extension type (theme or module).
   */
  private function getExtensionType(string $extension): string {
    if ($this->moduleHandler->moduleExists($extension)) {
      return 'module';
    }
    if ($this->themeHandler->themeExists($extension)) {
      return 'theme';
    }
    return '';
  }

}
