<?php

declare(strict_types=1);

namespace Drupal\preview_link\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for removing tokens from the session.
 */
class PreviewLinkSessionTokenController extends ControllerBase {

  /**
   * PreviewLinkSessionTokenController constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   The tempstore factory.
   */
  final public function __construct(
    TranslationInterface $stringTranslation,
    MessengerInterface $messenger,
    protected PrivateTempStoreFactory $privateTempStoreFactory,
  ) {
    $this->messenger = $messenger;
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('messenger'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * Removes tokens from the users' session.
   */
  public function removeTokens(): Response {
    $collection = $this->privateTempStoreFactory->get('preview_link');
    $hasKeys = $collection->get('keys') !== NULL;
    $collection->delete('keys');
    if ($hasKeys) {
      $this->messenger->addMessage($this->t('Removed preview link tokens.'));
    }
    else {
      $this->messenger->addWarning($this->t('No preview link tokens associated with this session.'));
    }

    $destination = Url::fromRoute('<front>');
    return new RedirectResponse($destination->toString());
  }

}
