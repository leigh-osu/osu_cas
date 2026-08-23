<?php

namespace Drupal\osu_story\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings for the OSU Story module.
 */
class OsuStorySettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'osu_story_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['osu_story.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('osu_story.settings');
    $form['auto_forward_external'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically forward external stories to their target'),
      '#description' => $this->t('When enabled, saving a story with an external URL creates a redirect, points its canonical/OG meta tags at the target, and excludes it from the XML sitemap — visitors who open the story are forwarded to the target. When disabled, external stories render as normal local pages: existing redirects are suppressed (not deleted) and no new ones are created; re-enabling restores the forwarding immediately.'),
      '#default_value' => $config->get('auto_forward_external') ?? TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $enabled = (bool) $form_state->getValue('auto_forward_external');
    $this->config('osu_story.settings')
      ->set('auto_forward_external', $enabled)
      ->save();
    if ($enabled) {
      // Stories created or edited while the feature was off have no
      // redirect; give them the full external treatment now.
      $repaired = _osu_story_auto_forward_catch_up();
      if ($repaired) {
        $this->messenger()->addStatus($this->formatPlural($repaired,
          '1 external story saved while forwarding was off now forwards too.',
          '@count external stories saved while forwarding was off now forward too.'));
      }
    }
    // The toggle must take effect immediately in both directions: cached
    // redirect responses carry only their redirect entity's cache tag, so
    // clear the page caches outright alongside the render cache.
    Cache::invalidateTags(['rendered']);
    \Drupal::cache('page')->deleteAll();
    \Drupal::cache('dynamic_page_cache')->deleteAll();
    parent::submitForm($form, $form_state);
  }

}
