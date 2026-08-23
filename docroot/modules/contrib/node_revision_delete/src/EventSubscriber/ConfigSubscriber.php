<?php

namespace Drupal\node_revision_delete\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeletePluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to clear the node revision delete plugin cache.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  public function __construct(#[Autowire(service: 'plugin.manager.node_revision_delete')] protected NodeRevisionDeletePluginManager $pluginManager) {
  }

  /**
   * Clears the node revision delete plugin cache when config changes.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSaveOrDelete(ConfigCrudEvent $event) {
    $saved_config = $event->getConfig();
    $name = $saved_config->getName();
    if ($name === 'node_revision_delete.settings' || str_starts_with($name, 'node.type.')) {
      $this->pluginManager->clearCachedDefinitions();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onConfigSaveOrDelete'];
    $events[ConfigEvents::DELETE][] = ['onConfigSaveOrDelete'];
    return $events;
  }

}
