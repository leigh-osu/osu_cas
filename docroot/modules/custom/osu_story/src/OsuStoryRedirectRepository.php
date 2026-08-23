<?php

namespace Drupal\osu_story;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\Language;
use Drupal\redirect\RedirectRepository;

/**
 * Redirect repository that honors the external-story auto-forward toggle.
 *
 * With auto forwarding disabled (/admin/config/content/osu-story), a
 * matched redirect whose source is a story node carrying an external URL
 * is suppressed — the story renders as an ordinary local page — without
 * deleting the redirect entity, so re-enabling restores the forwarding.
 */
class OsuStoryRedirectRepository extends RedirectRepository {

  /**
   * {@inheritdoc}
   */
  public function findMatchingRedirect($source_path, array $query = [], $language = Language::LANGCODE_NOT_SPECIFIED, ?CacheableMetadata $cacheable_metadata = NULL) {
    $redirect = parent::findMatchingRedirect($source_path, $query, $language, $cacheable_metadata);
    if (!$redirect || _osu_story_auto_forward_enabled()) {
      return $redirect;
    }
    if (preg_match('~^/?node/(\d+)$~', $redirect->getSourcePathWithQuery(), $m)) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($m[1]);
      if ($node && $node->bundle() === 'story'
        && $node->hasField('field_osu_story_external_url')
        && !$node->get('field_osu_story_external_url')->isEmpty()) {
        // The outcome depends on the toggle: cache accordingly.
        $cacheable_metadata?->addCacheTags(['config:osu_story.settings']);
        return NULL;
      }
    }
    return $redirect;
  }

}
