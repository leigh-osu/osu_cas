<?php

declare(strict_types=1);

namespace Drupal\osu_a11y_remediation\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\node\NodeInterface;
use Drupal\osu_editorial_workflow\Event\ContentModerationStateChangedEvent;
use Drupal\path_alias\AliasManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to content moderation events and handles state transitions.
 *
 * This class listens for content moderation state change events and provides
 * functionality to perform specific actions when a transition occurs in the
 * content moderation workflow. It is primarily used to execute custom logic
 * based on the defined workflows and states.
 */
final class OsuA11yRemediationWorkflowSubscriber implements EventSubscriberInterface {

  use AutowireTrait;
  use LoggerChannelTrait;

  /**
   * The Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AliasManagerInterface $aliasManager,
  ) {
    $this->logger = $this->getLogger('osu_editorial_workflow');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'content_moderation.state_changed' => 'onContentModerationTransition',
    ];
  }

  /**
   * Handles content moderation state transitions.
   *
   * @param \Drupal\content_moderation\Event\ContentModerationStateChangedEvent|\Drupal\osu_editorial_workflow\Event\ContentModerationStateChangedEvent|\Drupal\workbench_email\EventSubscriber\ContentModerationStateChangedEvent $event
   *   The Event listened to.
   */
  public function onContentModerationTransition(ContentModerationStateChangedEvent $event): void {
    $entity = $event->getModeratedEntity();
    $from = $event->getOriginalState();
    $to = $event->getNewState();
    if ($to === 'preserve') {
      $this->archiveAlias($entity);
      return;
    }
    if ($from === 'preserve') {
      $this->restoreAlias($entity);
    }
  }

  /**
   * Adds archive to the alias for a given node.
   *
   * When setting a node to this state, we add '/archive' prefix if not already
   * present.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which the alias is to be set.
   */
  private function archiveAlias(NodeInterface $node): void {
    try {
      $internalPath = "/" . $node->toUrl()->getInternalPath();
    }
    catch (EntityMalformedException | UndefinedLinkTemplateException $e) {
      $this->logger->error($e->getMessage());
      return;
    }
    $currentAlias = $this->aliasManager->getAliasByPath($internalPath);
    if (str_starts_with($currentAlias, '/archive/')) {
      return;
    }
    $newAlias = '/archive' . $currentAlias;
    $this->updateAlias($internalPath, $newAlias);
  }

  /**
   * Updates the alias for a given node path with a new alias.
   *
   * @param string $internalPath
   *   The internal path of the node.
   * @param string $newAlias
   *   The new alias to be set.
   */
  private function updateAlias(string $internalPath, string $newAlias): void {
    try {
      $pathAliasEntity = $this->entityTypeManager->getStorage('path_alias')
        ->loadByProperties(['path' => $internalPath]);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->error($e->getMessage());
      return;
    }
    if (!empty($pathAliasEntity)) {
      $pathAliasEntity = reset($pathAliasEntity);
      $pathAliasEntity->setAlias($newAlias);
      $pathAliasEntity->save();
      $this->aliasManager->cacheClear($internalPath);
    }
  }

  /**
   * Restores the alias for a given node.
   *
   * Restores the alias for a given node by removing the '/archive' prefix if
   * present.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which the alias is to be restored.
   */
  private function restoreAlias(NodeInterface $node): void {
    try {
      $internalPath = "/" . $node->toUrl()->getInternalPath();
    }
    catch (EntityMalformedException | UndefinedLinkTemplateException $e) {
      $this->logger->error($e->getMessage());
      return;
    }
    $currentAlias = $this->aliasManager->getAliasByPath($internalPath);
    if (!str_starts_with($currentAlias, '/archive/')) {
      return;
    }
    $newAlias = str_replace('/archive', '', $currentAlias);
    $this->updateAlias($internalPath, $newAlias);
  }

}
