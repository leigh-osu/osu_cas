<?php

declare(strict_types=1);

namespace Drupal\preview_link\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\preview_link\Exception\PreviewLinkRerouteException;
use Drupal\preview_link\PreviewLinkMessageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Modifies canonical entity routing to redirect to preview link.
 */
class PreviewLinkRouteEventSubscriber implements EventSubscriberInterface {

  /**
   * PreviewLinkRouteEventSubscriber constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\preview_link\PreviewLinkMessageInterface $previewLinkMessages
   *   Provides common messenger functionality.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    protected MessengerInterface $messenger,
    protected PreviewLinkMessageInterface $previewLinkMessages,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Redirects from canonical routes to preview link route.
   *
   * Need to use GetResponseForExceptionEvent and getException method instead of
   * ExceptionEvent::getThrowable() since these are in Symfony 4.4, and
   * Drupal 8.9 supports Symfony 3.4.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    if ($exception instanceof PreviewLinkRerouteException) {
      $entity = $exception->getEntity();

      $token = $exception->getPreviewLink()->getToken();
      $previewLinkUrl = Url::fromRoute('entity.' . $entity->getEntityTypeId() . '.preview_link', [
        $entity->getEntityTypeId() => $entity->id(),
        'preview_token' => $token,
      ]);

      // This message will display for subsequent page loads.
      // Message is designed to only be visible on canonical -> preview link
      // redirects, not on preview link routes accessed directly.
      $config = $this->configFactory->get('preview_link.settings');
      // 'always' includes subsequent.
      if (in_array($config->get('display_message'), ['always', 'subsequent'], TRUE)) {
        // Redirect destination actually has the canonical route since that's
        // where we are right now.
        $this->messenger->addMessage($this->previewLinkMessages->getGrantMessage($entity->toUrl()));
      }

      $this->logger->debug('Redirecting to preview link of @entity', [
        '@entity' => $entity->label(),
      ]);

      // 307: temporary.
      $response = (new TrustedRedirectResponse($previewLinkUrl->toString(), TrustedRedirectResponse::HTTP_TEMPORARY_REDIRECT))
        ->addCacheableDependency($exception);
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Needs to be higher than ExceptionLoggingSubscriber::onError (priority 50)
    // so exception is not logged. Larger numbers are earlier:
    return [
      KernelEvents::EXCEPTION => [['onException', 51]],
    ];
  }

}
