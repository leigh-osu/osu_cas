<?php

namespace Drupal\domain_config_extras;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\domain_config\Config\DomainConfigCollectionUtils;

/**
 * Auxiliary functions for operating with domains and configuration overrides.
 */
class DomainConfigUtilities {

  /**
   * The domain entity storage.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * Constructs a DomainConfigUtilities object.
   *
   * @param \Drupal\Core\Config\StorageInterface $configStorage
   *   The configuration storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    protected StorageInterface $configStorage,
    EntityTypeManagerInterface $entity_type_manager,
    protected LanguageManagerInterface $languageManager,
  ) {
    $this->domainStorage = $entity_type_manager->getStorage('domain');
  }

  /**
   * Return config overrides across all (or only active) domains.
   *
   * @param array $names
   *   A list of configuration names that are being loaded.
   * @param bool $only_active
   *   Whether to load overrides only for active domains or all.
   *
   * @return array
   *   An array keyed by domain name, then configuration name of override data.
   *   Override data contains a nested array structure of overrides.
   */
  public function loadAllDomainOverrides(array $names, $only_active = FALSE): array {
    $result = [];

    if ($only_active) {
      $domains = $this->domainStorage->loadByProperties(['active' => 1]);
    }
    else {
      $domains = $this->domainStorage->loadMultiple();
    }
    $languages = $this->languageManager->getLanguages();

    /** @var \Drupal\domain\DomainInterface $domain */
    foreach ($domains as $domain) {
      $domain_collection_name =
        DomainConfigCollectionUtils::createDomainConfigCollectionName($domain->id());
      $domain_collection = $this->configStorage->createCollection($domain_collection_name);
      foreach ($names as $name) {
        // Check domain overridden configuration.
        if ($domain_collection->exists($name)) {
          $result[$name][$domain->id()]['default'] = $domain_collection->read($name);
        }
      }
      foreach ($languages as $language) {
        // Check domain with language overridden configuration.
        $language_collection_name =
          DomainConfigCollectionUtils::createDomainLanguageConfigCollectionName($domain->id(), $language->getId());
        $domain_language_collection = $this->configStorage->createCollection($language_collection_name);
        foreach ($names as $name) {
          if ($domain_language_collection->exists($name)) {
            $result[$name][$domain->id()][$language->getId()] = $domain_language_collection->read($name);
          }
        }
      }
    }

    return $result;
  }

}
