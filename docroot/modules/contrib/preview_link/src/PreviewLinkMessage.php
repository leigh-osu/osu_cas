<?php

declare(strict_types=1);

namespace Drupal\preview_link;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Provides common messenger functionality.
 */
class PreviewLinkMessage implements PreviewLinkMessageInterface {

  use StringTranslationTrait;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * PreviewLinkMessage constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(TranslationInterface $stringTranslation, AccountInterface $currentUser) {
    $this->stringTranslation = $stringTranslation;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public function getGrantMessage(Url $destination): TranslatableMarkup {
    // Push the user back, but only if they have permission to view the
    // destination..
    $removeUrl = Url::fromRoute('preview_link.session_tokens.remove');
    $canAccessNonPreviewLinkVersion = FALSE;
    try {
      if ($destination->access($this->currentUser)) {
        $removeUrl->setOption('query', ['destination' => $destination->toString()]);
        $canAccessNonPreviewLinkVersion = TRUE;
      }
    }
    catch (\InvalidArgumentException $e) {
    }

    $tArgs = ['@remove_session_url' => $removeUrl->toString()];
    if ($canAccessNonPreviewLinkVersion) {
      return $this->t('You are viewing this page because a preview link granted you access. Click <a href="@remove_session_url">here</a> to remove token and go back to the current version of this page.', $tArgs);
    }
    else {
      return $this->t('You are viewing this page because a preview link granted you access. Click <a href="@remove_session_url">here</a> to remove token.', $tArgs);
    }
  }

}
