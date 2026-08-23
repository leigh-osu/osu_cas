<?php

/**
 * @file
 * Hooks provided by the Domain Config Entity UI submodule.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the registry of config entity storage swaps.
 *
 * The submodule auto-populates the swaps map with every config
 * entity type whose default storage_class is core's
 * \Drupal\Core\Config\Entity\ConfigEntityStorage, mapping each to the
 * shared
 * \Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorage
 * variant. Modules that ship their own DomainAware* storage subclass
 * for an entity type whose default handler is a custom subclass
 * (image_style → ImageStyleStorage, user_role → RoleStorage,
 * menu → MenuStorage, …) register their entries via this hook.
 *
 * Each map entry is keyed by the entity type id and holds a tuple of
 * [domain-aware storage class, expected current storage class]. The
 * strict-equality guard in hook_entity_type_alter() compares the
 * expected current class against the actual storage_class of the
 * entity type at discovery time, and only applies the swap when they
 * match. This preserves any custom storage_class set by another
 * module — if you want to chain on top of someone else's custom
 * handler, your DomainAware* class must extend their class and
 * declare it as the expected current class.
 *
 * Modules can also REMOVE auto-populated entries here to opt an
 * entity type out of coverage entirely (it would then never appear
 * on the SettingsForm).
 *
 * The user's actual choice of which available swaps to enable is a
 * separate concern, stored in
 * domain_config_entity_ui.settings.covered_entity_types and edited
 * via the SettingsForm. Adding an entry here only makes the entity
 * type available; it does not enable coverage on its own.
 *
 * @param array<string, array{0: class-string, 1: class-string}> $swaps
 *   The map keyed by entity type id.
 *
 * @see \Drupal\domain_config_entity_ui\DomainAwareSwapRegistry
 * @see \Drupal\domain_config_entity_ui\Hook\DomainConfigEntityUiEntityTypeHooks
 */
function hook_domain_config_entity_ui_swaps_alter(array &$swaps): void {
  // Example: a contrib module that ships DomainAwareImageStyleStorage
  // (extends ImageStyleStorage, uses
  // DomainAwareConfigEntityStorageTrait) registers its swap.
  $swaps['image_style'] = [
    'Drupal\\my_module\\Entity\\DomainAwareImageStyleStorage',
    'Drupal\\image\\ImageStyleStorage',
  ];
}

/**
 * @} End of "addtogroup hooks".
 */
