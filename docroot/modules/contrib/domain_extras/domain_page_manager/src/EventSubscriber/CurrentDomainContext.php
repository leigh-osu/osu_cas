<?php

namespace Drupal\domain_page_manager\EventSubscriber;

use Drupal\Component\Plugin\Context\ContextInterface;
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
    // Only when there is one. This event fires wherever a page variant's access
    // is evaluated, and that is not always inside a negotiated request: a cold
    // permission cache is recalculated on kernel.request, which pulls language
    // negotiation, which matches a route, which reaches page_manager before any
    // domain has been resolved. The provider then yields nothing, and passing
    // that straight on raises a TypeError on the argument's type declaration. A
    // page evaluated without a domain simply has no domain context.
    if ($context instanceof ContextInterface) {
      $event->getPage()->addContext('domain', $context);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[PageManagerEvents::PAGE_CONTEXT][] = 'onPageContext';
    return $events;
  }

}
