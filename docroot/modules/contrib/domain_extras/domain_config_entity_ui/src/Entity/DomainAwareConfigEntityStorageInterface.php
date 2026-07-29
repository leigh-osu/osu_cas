<?php

namespace Drupal\domain_config_entity_ui\Entity;

/**
 * Marks a config entity storage handler as domain-aware.
 *
 * The form_alter that exposes the "Enable domain configuration"
 * toggle on EntityForm-based config-entity edit pages and the
 * higher-priority ParamConverter both gate on this interface — they
 * only act when the entity type's storage handler is an instance,
 * so the toggle never appears (and the converter never intercepts)
 * for entity types whose storage cannot fold per-domain overrides
 * on the override-free read path.
 *
 * Adding a new entity type to coverage is a sibling subclass of its
 * existing storage handler that uses
 * DomainAwareConfigEntityStorageTrait and implements this interface,
 * plus one entry in DomainConfigEntityUiEntityTypeHooks::entityTypeAlter().
 *
 * @see \Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorageTrait
 * @see \Drupal\domain_config_entity_ui\Hook\DomainConfigEntityUiEntityTypeHooks
 */
interface DomainAwareConfigEntityStorageInterface {
}
