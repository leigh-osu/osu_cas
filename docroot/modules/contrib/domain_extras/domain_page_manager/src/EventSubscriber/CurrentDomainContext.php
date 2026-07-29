<?php

namespace Drupal\domain_page_manager\EventSubscriber;

use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\page_manager\Event\PageManagerContextEvent;
use Drupal\page_manager\Event\PageManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets the current domain as a context.
 */
class CurrentDomainContext implements EventSubscriberInterface {

  /**
   * Creates LanguageInterfaceContext object.
   *
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $contextRepository
   *   The context repository service.
   */
  public function __construct(
    protected ContextRepositoryInterface $contextRepository,
  ) {}

  /**
   * Adds in the current domain as a context.
   *
   * @param \Drupal\page_manager\Event\PageManagerContextEvent $event
   *   The page entity context event.
   */
  public function onPageContext(PageManagerContextEvent $event) {
    $contexts = $this->contextRepository->getRuntimeContexts(['@domain.current_domain_context:domain']);
    $context = reset($contexts);
    $event->getPage()->addContext('domain', $context);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[PageManagerEvents::PAGE_CONTEXT][] = 'onPageContext';
    return $events;
  }

}
