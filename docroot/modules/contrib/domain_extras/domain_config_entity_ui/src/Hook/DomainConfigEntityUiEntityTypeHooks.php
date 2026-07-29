<?php

namespace Drupal\domain_config_entity_ui\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\domain_config_entity_ui\DomainAwareSwapRegistry;

/**
 * Entity type hook implementations for domain_config_entity_ui.
 */
class DomainConfigEntityUiEntityTypeHooks {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected DomainAwareSwapRegistry $swapRegistry,
  ) {}

  /**
   * Implements hook_entity_type_alter().
   *
   * Replaces the storage_class on entity types the user has enabled
   * via the SettingsForm with a DomainAware variant so override-free
   * reads (ConfigEntityListBuilder::load, AdminPathConfigEntityConverter,
   * BlockListBuilder::submitForm, drush, REST, custom code) fold in
   * the active domain's registered overrides. Save flows are
   * unchanged: ConfigEntityStorage::doSave() delegates to ConfigFactory,
   * and DomainConfigFactory's existing override routing applies.
   *
   * Two opt-in layers: installing this submodule is the gross gate
   * (it must be enabled at all for any swap to happen); the
   * per-entity-type checkboxes on the SettingsForm are the fine
   * gate (the user picks which types to cover).
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) {
    $covered = $this->configFactory
      ->get('domain_config_entity_ui.settings')
      ->get('covered_entity_types') ?? [];
    if (empty($covered)) {
      return;
    }
    $swaps = $this->swapRegistry->computeSwaps($entity_types);
    foreach ($swaps as $entity_type_id => [$domain_aware_class, $expected_current_class]) {
      if (!in_array($entity_type_id, $covered, TRUE)) {
        continue;
      }
      if (!isset($entity_types[$entity_type_id])) {
        continue;
      }
      // Strict-equality guard preserves contrib subclasses.
      if ($entity_types[$entity_type_id]->getStorageClass() !== $expected_current_class) {
        continue;
      }
      $entity_types[$entity_type_id]->setStorageClass($domain_aware_class);
    }
  }

}
