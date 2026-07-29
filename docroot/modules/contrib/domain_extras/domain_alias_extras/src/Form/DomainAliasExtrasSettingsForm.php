<?php

namespace Drupal\domain_alias_extras\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;

/**
 * Settings for the module.
 *
 * @package Drupal\domain_alias_extras\Form
 */
class DomainAliasExtrasSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_alias_extras_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['environments'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Development environments'),
      '#description' => $this->t('Enter one environment per line. The default environment should always be included.'),
      '#rows' => 10,
      '#required' => TRUE,
      '#config_target' => 'domain_alias.settings:environments',
      '#process' => [[$this, 'processEnvironments']],
      '#element_validate' => [[$this, 'validateEnvironments']],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Process the environments textarea.
   */
  public function processEnvironments(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if (isset($element['#default_value']) && is_array($element['#default_value'])) {
      $element['#default_value'] = implode("\n", $element['#default_value']);
    }
    if (isset($element['#value']) && is_array($element['#value'])) {
      $element['#value'] = implode("\n", $element['#value']);
    }
    return $element;
  }

  /**
   * Validate and process the environments textarea.
   */
  public function validateEnvironments(array &$element, FormStateInterface $form_state) {
    $value = $form_state->getValue('environments');

    // Convert textarea to array, trim whitespace, and filter empty values.
    $environments = array_filter(
      array_map('trim', explode("\n", $value)),
      fn($val) => $val !== ''
    );

    // Ensure we have at least the default environment.
    if (!in_array('default', $environments, TRUE)) {
      array_unshift($environments, 'default');
    }

    // Re-index the array to ensure sequential keys for YAML sequence.
    $environments = array_values($environments);

    // Set the processed array value.
    $form_state->setValue('environments', $environments);
  }

}
