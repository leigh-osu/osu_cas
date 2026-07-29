<?php

namespace Drupal\domain_config_entity_ui\Entity;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\domain_config_ui\DomainConfigUIManagerInterface;

/**
 * Folds the active domain's override on top of override-free reads.
 *
 * Drupal core's ConfigEntityListBuilder::load(), the
 * AdminPathConfigEntityConverter, and a few other call sites
 * (BlockListBuilder::submitForm, …) read entities through
 * loadMultipleOverrideFree() — strict base, no runtime overrides. From a
 * domain admin's perspective the per-domain override is not a runtime
 * overlay but the configured value for that domain, so override-free
 * reads should fold the domain layer back on for configurations
 * registered as overridable for the active domain. Other overlays
 * (settings.php, language, …) stay stripped, matching the contract of
 * the override-free read.
 *
 * Regular load()/loadMultiple() calls (saves, runtime page renders,
 * drush, …) are left untouched: the !$this->overrideFree short-circuit
 * defers to the parent storage's behavior, where the regular
 * ConfigFactoryOverrideInterface stack already applies the right merge.
 *
 * Each storage subclass that uses this trait must implement
 * DomainAwareConfigEntityStorageInterface so the form_alter / converter
 * can detect coverage by introspection.
 */
trait DomainAwareConfigEntityStorageTrait {

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(?array $ids = NULL) {
    $entities = parent::doLoadMultiple($ids);
    if (
      !$this->overrideFree
      || $entities === []
      || !$this->entityType instanceof ConfigEntityTypeInterface
    ) {
      return $entities;
    }

    $manager = \Drupal::service(DomainConfigUIManagerInterface::class);
    $domain_id = $manager->getActiveDomainId();
    if (!$domain_id) {
      // CLI, install hooks, anything else without an active domain.
      return $entities;
    }

    $domain_override_factory = \Drupal::service('domain.config_factory_override');
    $domain_storage = $domain_override_factory->getStorage($domain_id);
    $config_prefix = $this->entityType->getConfigPrefix();

    foreach ($entities as $id => $entity) {
      $config_name = $config_prefix . '.' . $id;
      if (
        !$manager->isAllowedConfiguration([$config_name])
        || !$manager->isConfigurationRegisteredForDomain($domain_id, [$config_name])
      ) {
        continue;
      }
      $override_data = $domain_storage->read($config_name);
      if (!is_array($override_data) || $override_data === []) {
        continue;
      }
      foreach ($override_data as $key => $value) {
        $entity->set($key, $value);
      }
      $entity->addCacheableDependency($domain_override_factory->getCacheableMetadata($config_name));
    }

    return $entities;
  }

}
