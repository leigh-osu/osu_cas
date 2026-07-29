<?php

namespace Drupal\domain_early_negotiation\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;

/**
 * Settings form for Domain Early Negotiation.
 */
class DomainEarlyNegotiationSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_early_negotiation_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['priority'] = [
      '#type' => 'number',
      '#title' => $this->t('Middleware priority'),
      '#config_target' => 'domain_early_negotiation.settings:priority',
      '#description' => $this->t(
        'Higher values run earlier. Must be lower than 300 (ReverseProxy) and higher than any middleware that needs domain_config overrides. Values above 200 run before the page cache, meaning domain negotiation executes on every request including cached pages. On Drupal versions before 11.1, this also forces all module files to load.'
      ),
      '#min' => 1,
      '#max' => 299,
    ];
    return parent::buildForm($form, $form_state);
  }

}
