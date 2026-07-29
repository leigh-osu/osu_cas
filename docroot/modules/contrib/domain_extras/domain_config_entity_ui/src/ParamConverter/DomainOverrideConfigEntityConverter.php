<?php

namespace Drupal\domain_config_entity_ui\ParamConverter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\AdminPathConfigEntityConverter;
use Drupal\Core\Routing\AdminContext;
use Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorageInterface;
use Drupal\domain_config_ui\DomainConfigUIManagerInterface;
use Symfony\Component\Routing\Route;

/**
 * Loads the override-merged config entity on admin paths when registered.
 *
 * Drupal core's AdminPathConfigEntityConverter unconditionally loads config
 * entities override-free on admin paths, which means an EntityForm-based
 * edit form (block, view mode, search page, …) renders the base values even
 * when a per-domain override exists for the active domain. This converter
 * runs at higher priority and, when the config is registered as overridable
 * for the active domain, loads with overrides so the form reflects what the
 * domain user actually edits. For everything else it defers to core's
 * override-free behavior.
 *
 * Installing this submodule is the opt-in — there is no runtime flag.
 */
class DomainOverrideConfigEntityConverter extends AdminPathConfigEntityConverter {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    AdminContext $admin_context,
    EntityRepositoryInterface $entity_repository,
    protected DomainConfigUIManagerInterface $manager,
  ) {
    parent::__construct($entity_type_manager, $config_factory, $admin_context, $entity_repository);
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return NULL;
    }
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    // Defer to core's override-free behavior for non-config entity types;
    // narrowing on ConfigEntityTypeInterface here also lets static analysis
    // see getConfigPrefix() below — the parent EntityTypeInterface does
    // not declare it.
    if (!$entity_type instanceof ConfigEntityTypeInterface) {
      return parent::convert($value, $definition, $name, $defaults);
    }
    // Capability gate: the storage must be domain-aware. For everything
    // else core's override-free behavior is correct and is what the
    // form_alter declines to expose the toggle for anyway.
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$storage instanceof DomainAwareConfigEntityStorageInterface) {
      return parent::convert($value, $definition, $name, $defaults);
    }

    $config_name = $entity_type->getConfigPrefix() . '.' . $value;
    if (
      $this->manager->getActiveDomainId()
      && $this->manager->isAllowedConfiguration([$config_name])
      && $this->manager->isRegisteredConfiguration([$config_name])
    ) {
      // Load with overrides so the edit form reflects the per-domain values.
      return $storage->load($value);
    }

    return parent::convert($value, $definition, $name, $defaults);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    if (!parent::applies($definition, $name, $route)) {
      return FALSE;
    }
    // Only claim the route when convert() will actually branch into the
    // override-aware path. Otherwise we tie on priority 10 with other
    // entity:* converters (notably views_ui's ViewUIConverter, which
    // depends on running for tempstore: TRUE routes) and may displace
    // them at route-rebuild time, leaving the controller with a bare
    // entity instead of the wrapped UI variant.
    //
    // The entity type is only known when the type slug is hardcoded
    // (entity:foo). Routes that use a dynamic placeholder
    // (entity:{entity_type}) cannot be resolved here without the
    // request's defaults, so decline — convert() can still take effect
    // through core's dispatch when no other converter applies.
    $type_slug = substr($definition['type'], strlen('entity:'));
    if (str_starts_with($type_slug, '{')) {
      return FALSE;
    }
    if (!$this->entityTypeManager->hasDefinition($type_slug)) {
      return FALSE;
    }
    $entity_type = $this->entityTypeManager->getDefinition($type_slug);
    if (!$entity_type instanceof ConfigEntityTypeInterface) {
      return FALSE;
    }
    return $this->entityTypeManager->getStorage($type_slug)
      instanceof DomainAwareConfigEntityStorageInterface;
  }

}
