<?php

namespace Drupal\osu_editorial_workflow\Event;

/**
 * Defines events that content_moderation dispatches.
 *
 * @see \Drupal\osu_editorial_workflow\Event\ContentModerationStateChangedEvent
 */
final class ContentModerationEvents {

  /**
   * Name of the event fired when content changes state.
   *
   * @see \Drupal\osu_editorial_workflow\Event\ContentModerationStateChangedEvent
   * @see \Drupal\content_moderation\Entity\ContentModerationState::realSave()
   */
  const STATE_CHANGED = 'content_moderation.state_changed';

}
