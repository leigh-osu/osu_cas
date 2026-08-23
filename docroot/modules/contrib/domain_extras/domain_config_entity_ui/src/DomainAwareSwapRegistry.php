<?php

namespace Drupal\domain_config_entity_ui;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorage;
use Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorageInterface;

/**
 * Registry of available config entity storage_class swaps.
 *
 * Auto-discovers every config entity type whose default storage_class
 * is core's ConfigEntityStorage and registers a swap to
 * DomainAwareConfigEntityStorage for each. Modules can extend the
 * registry — typically to ship a sibling DomainAware* subclass for an
 * entity type that has its own custom storage handler — by
 * implementing hook_domain_config_entity_ui_swaps_alter(); see
 * domain_config_entity_ui.api.php for the contract.
 *
 * The user's actual choice of which available swaps to apply is a
 * separate concern stored in
 * domain_config_entity_ui.settings.covered_entity_types and edited
 * via the SettingsForm.
 */
class DomainAwareSwapRegistry {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the swaps map, computed against the live entity_type.manager.
   *
   * Use from anywhere outside hook_entity_type_alter — the SettingsForm
   * for instance, which renders post-discovery and needs to enumerate
   * the available checkboxes.
   *
   * @return array<string, array{0: class-string, 1: class-string}>
   *   Map keyed by entity type id, each value being a tuple of
   *   [domain-aware storage class, expected current storage class].
   */
  public function getSwaps(): array {
    return $this->computeSwaps($this->entityTypeManager->getDefinitions());
  }

  /**
   * Computes the swaps map from a given entity-type definition array.
   *
   * Use from inside hook_entity_type_alter, where the passed-in
   * $entity_types is the in-progress definitions array — calling
   * entity_type.manager during an alter would re-trigger the alter
   * recursively.
   *
   * @param array<string, \Drupal\Core\Entity\EntityTypeInterface> $entity_types
   *   The definitions to inspect.
   *
   * @return array<string, array{0: class-string, 1: class-string}>
   *   Map keyed by entity type id.
   */
  public function computeSwaps(array $entity_types): array {
    $swaps = [];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ConfigEntityTypeInterface) {
        continue;
      }
      // Match either the un-swapped vanilla state, or any already-
      // swapped state — both signal "this entity type's default
      // storage handler is vanilla and this submodule can cover it."
      // Filtering on ConfigEntityStorage alone would lose the type
      // from the registry as soon as the swap was applied, hiding it
      // from the SettingsForm checkbox list once enabled. Checking
      // the marker interface (rather than the specific
      // DomainAwareConfigEntityStorage class) keeps the contract
      // implementation-agnostic: any contrib subclass that ships its
      // own DomainAware* handler is automatically detected once the
      // alter hook has registered it.
      $current_class = $entity_type->getStorageClass();
      if (
        $current_class !== ConfigEntityStorage::class
        && !is_subclass_of($current_class, DomainAwareConfigEntityStorageInterface::class)
      ) {
        continue;
      }
      $swaps[$entity_type_id] = [
        DomainAwareConfigEntityStorage::class,
        ConfigEntityStorage::class,
      ];
    }
    // Modules can append entries for entity types whose default
    // storage_class is a custom subclass — those need a sibling
    // DomainAware* subclass that extends the type's own handler.
    $this->moduleHandler->alter('domain_config_entity_ui_swaps', $swaps);
    return $swaps;
  }

}
