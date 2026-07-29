<?php

namespace Drupal\domain_config_entity_ui\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invalidates entity type definitions when covered_entity_types changes.
 *
 * DomainConfigEntityUiEntityTypeHooks::entityTypeAlter() reads the
 * covered_entity_types selection at entity-type-discovery time and
 * caches the resulting storage_class assignments alongside the
 * definitions. Without this subscriber the user would have to run
 * `drush cr` (or wait for the cache to expire) before a checkbox
 * change took effect; clearing the entity type definitions cache on
 * save lets the next request re-run the alter against the new value.
 */
class DomainConfigEntityUiSettingsSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Reacts to domain_config_entity_ui.settings saves.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    if (
      $event->getConfig()->getName() === 'domain_config_entity_ui.settings'
      && $event->isChanged('covered_entity_types')
    ) {
      $this->entityTypeManager->clearCachedDefinitions();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::SAVE => 'onConfigSave'];
  }

}
