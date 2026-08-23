<?php

namespace Drupal\osu_editorial_workflow\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines content Moderation State Changed events.
 *
 * @see \Drupal\content_moderation\Entity\ContentModerationState
 * @see \Drupal\osu_editorial_workflow\Event\ContentModerationEvents
 */
class ContentModerationStateChangedEvent extends Event {

  use AutowireTrait;

  public function __construct(
    private readonly ContentEntityInterface $moderatedEntity,
    private readonly string $newState,
    private readonly string $originalState,
    private readonly string $workflow,
  ) {}

  /**
   * Get the entity that is being moderated.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity that is being moderated.
   */
  public function getModeratedEntity(): ContentEntityInterface {
    return $this->moderatedEntity;
  }

  /**
   * Get the new state of the entity.
   *
   * @return string
   *   The state the content has been changed to.
   */
  public function getNewState(): string {
    return $this->newState;
  }

  /**
   * Get the original state of the entity.
   *
   * @return string
   *   The state the content was in.
   */
  public function getOriginalState(): string {
    return $this->originalState;
  }

  /**
   * Get the ID of the workflow.
   *
   * @return string
   *   The ID of the workflow.
   */
  public function getWorkflow(): string {
    return $this->workflow;
  }

}
