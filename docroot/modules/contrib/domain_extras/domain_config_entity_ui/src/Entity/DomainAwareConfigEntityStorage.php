<?php

namespace Drupal\domain_config_entity_ui\Entity;

use Drupal\Core\Config\Entity\ConfigEntityStorage;

/**
 * Domain-aware drop-in replacement for vanilla ConfigEntityStorage.
 *
 * Used as the storage_class for config entity types whose default
 * handler is core's ConfigEntityStorage (block, view modes, search
 * pages, views, …). The trait holds the override-free overlay
 * semantics; this class is a thin shell that ties it to the
 * ConfigEntityStorage parent and announces capability via the marker
 * interface so the form_alter and ParamConverter can gate their UI
 * on it.
 *
 * Entity types with their own ConfigEntityStorage subclass (image_style
 * → ImageStyleStorage, user_role → RoleStorage, …) get a sibling
 * DomainAware*Storage class that extends the type's own subclass, uses
 * the same trait, and implements the same interface. We do not bulk-
 * loop "any extender of ConfigEntityStorage" — we curate each entity
 * type so the strict-equality storage_class swap can preserve contrib
 * subclasses untouched.
 *
 * @see \Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorageInterface
 * @see \Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorageTrait
 * @see \Drupal\domain_config_entity_ui\Hook\DomainConfigEntityUiEntityTypeHooks
 */
class DomainAwareConfigEntityStorage extends ConfigEntityStorage implements DomainAwareConfigEntityStorageInterface {

  use DomainAwareConfigEntityStorageTrait;

}
