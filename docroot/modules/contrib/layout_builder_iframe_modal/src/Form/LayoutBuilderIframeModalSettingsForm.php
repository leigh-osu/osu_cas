<?php

declare(strict_types=1);

namespace Drupal\layout_builder_iframe_modal\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for Layout Builder Iframe Modal settings.
 */
class LayoutBuilderIframeModalSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'layout_builder_iframe_modal';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['layout_builder_iframe_modal.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $options = [
      'layout_builder.configure_section' => $this->t('Configure section'),
      'layout_builder.remove_section' => $this->t('Remove section'),
      'layout_builder.remove_block' => $this->t('Remove block'),
      'layout_builder.add_section' => $this->t('Add section'),
      'layout_builder.add_block' => $this->t('Add block'),
      'layout_builder.update_block' => $this->t('Update block'),
      'layout_builder.move_sections_form' => $this->t('Reorder sections'),
      'layout_builder.move_block_form' => $this->t('Move block'),
      'layout_builder.translate_block' => $this->t('Translate block'),
      'layout_builder.translate_inline_block' => $this->t('Translate inline block'),
    ];

    foreach ($options as $key => $label) {
      $options[$key] = $label . " ($key)";
    }

    $form['layout_builder_iframe_routes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Layout Builder Routes'),
      '#description' => $this->t('Select Layout Builder routes to apply iframe to.'),
      '#options' => $options,
      '#config_target' => new ConfigTarget(
        'layout_builder_iframe_modal.settings',
        'layout_builder_iframe_routes',
        // Converts config value to a form value.
        NULL,
        // Converts form value to a config value.
        fn ($value) => array_keys(array_filter($value)),
      ),
    ];

    $form['custom_routes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom routes'),
      '#description' => $this->t('Add custom routes (one per line) that should also open in an iframe modal. Note that it can only apply to routes which are already configured to open in a dialog. It may require additional code for the route to actually open in an iframe modal.'),
      '#config_target' => new ConfigTarget(
        'layout_builder_iframe_modal.settings',
        'custom_routes',
        // Converts config value to a form value.
        fn($value) => is_array($value) ? implode("\n", $value) : '',
        // Converts form value to a config value.
        fn($value) => array_filter(array_map('trim', explode("\n", trim($value)))),
      ),
    ];

    return parent::buildForm($form, $form_state);
  }

}
