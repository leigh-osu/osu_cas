<?php

namespace Drupal\date_ical\Plugin\CKEditor5Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Address Suggestion plugin.
 */
class DateICal extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration() {
    return [
      'description' => FALSE,
      'location' => FALSE,
      'categories' => FALSE,
      'organizer' => FALSE,
      'url' => FALSE,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Description'),
      '#default_value' => $this->configuration['description'],
    ];
    $form['location'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Location'),
      '#default_value' => $this->configuration['location'],
    ];
    $form['categories'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Categories'),
      '#default_value' => $this->configuration['categories'],
    ];
    $form['organizer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Organizer'),
      '#default_value' => $this->configuration['organizer'],
    ];
    $form['url'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('URL'),
      '#default_value' => $this->configuration['url'],
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['description'] = (bool) $form_state->getValue('description') ?? FALSE;
    $this->configuration['location'] = (bool) $form_state->getValue('location') ?? FALSE;
    $this->configuration['categories'] = (bool) $form_state->getValue('categories') ?? FALSE;
    $this->configuration['organizer'] = (bool) $form_state->getValue('organizer') ?? FALSE;
    $this->configuration['url'] = (bool) $form_state->getValue('url') ?? FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Get options values in editor config.
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $url = Url::fromRoute('date_ical.download');
    $this->configuration['download'] = $url->toString();
    return [
      'date_ical' => $this->configuration,
    ];
  }

}
