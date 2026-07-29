<?php

namespace Drupal\domain_early_negotiation\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\DrupalKernel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invalidates the container when the priority setting changes.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  public function __construct(
    #[Autowire(service: 'kernel')]
    private DrupalKernel $kernel,
  ) {}

  /**
   * Rebuilds the container when priority changes.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $saved_config = $event->getConfig();
    if (
      !$saved_config->isNew()
      && $saved_config->getName() === 'domain_early_negotiation.settings'
      && $event->isChanged('priority')
    ) {
      $this->kernel->invalidateContainer();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => ['onConfigSave', 0],
    ];
  }

}
