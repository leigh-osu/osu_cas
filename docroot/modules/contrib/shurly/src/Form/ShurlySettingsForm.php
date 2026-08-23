<?php

namespace Drupal\shurly\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * ShurlySettingsForm.
 */
class ShurlySettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'shurly.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shurly_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('shurly.settings');

    $form['shurly_url'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Base URL'),
      '#description' => $this->t('If you want to use a dedicated url for the short URLs, enter above that short base URL to be used.'),
    ];

    $form['shurly_url']['shurly_base'] = [
      '#type' => 'textfield',
      '#description' => $this->t('Leave empty to use the default base URL of the Drupal installation'),
      '#default_value' => $config->get('shurly_base'),
      '#required' => FALSE,
    ];

    $form['shurly_redirect'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Redirect URL'),
      '#description' => $this->t('Define the redirect page when the short link is deactivated.'),
    ];

    $form['shurly_redirect']['shurly_redirect_page'] = [
      '#type' => 'textfield',
      '#field_prefix' => _shurly_get_shurly_base() . '/',
      '#description' => $this->t('Page displayed when the link is deactivated. If not defined, the default 404 page will be used.'),
      '#default_value' => $config->get('shurly_redirect_page'),
    ];

    $form['shurly_restrictions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Restrictions'),
      '#description' => $this->t('Restrict short URL targets. Be aware of the fact, that the localhost, local network and unresolvable restriction are resolving the given address by staging a DNS request, which can significantly <strong>slow down the short URL creation</strong>!'),
    ];

    $form['shurly_restrictions']['shurly_forbid_localhost'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Forbid localhost'),
      '#description' => $this->t('Do not allow creation of short URLs targeting localhost addresses.'),
      '#default_value' => $config->get('shurly_forbid_localhost'),
    ];

    $form['shurly_restrictions']['shurly_forbid_private_ips'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Forbid private IP ranges'),
      '#description' => $this->t('Do not allow creation of short URLs targeting private IP ranges.'),
      '#default_value' => $config->get('shurly_forbid_private_ips'),
    ];

    $form['shurly_restrictions']['shurly_forbid_unresolvable_hosts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Forbid unresolvable hostnames'),
      '#description' => $this->t('Do not allow creation of short URLs targeting host addresses that cannot be resolved.'),
      '#default_value' => $config->get('shurly_forbid_unresolvable_hosts'),
    ];

    $form['shurly_restrictions']['shurly_forbid_ips'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Forbid direct IP redirects'),
      '#description' => $this->t('Do not allow creation of short URLs containing an IP address instead of a human readable hostname.'),
      '#default_value' => $config->get('shurly_forbid_ips'),
    ];

    $form['shurly_restrictions']['shurly_forbid_custom'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Forbid URL target by custom pattern'),
      '#description' => $this->t('Define a custom pattern (RegEx) to forbid some kind of target URLs.'),
      '#default_value' => $config->get('shurly_forbid_custom'),
      '#attributes' => [
        'onchange' => "jQuery('#shurly_custom_restriction_container').toggle();",
      ],
    ];

    $form['shurly_restrictions']['shurly_custom_restriction'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom pattern'),
      '#description' => $this->t('PERL regular expression defining a forbidden URL pattern.'),
      '#default_value' => $config->get('shurly_custom_restriction'),
    ];

    $form['shurly_restrictions']['shurly_gsb'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Google Safe Browsing'),
      '#description' => $this->t('Check if a long URL is blacklisted against Google Safe Browsing. This service
        requires a Google developer account and is limited to 10,000 queries per day.'),
      '#default_value' => $config->get('shurly_gsb'),
      '#attributes' => [
        'onchange' => "jQuery('.shurly_gsb_container').toggle();",
      ],
    ];

    $form['shurly_restrictions']['shurly_gsb_client'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client'),
      '#description' => $this->t('You can choose any name. Google suggests that you choose a name that represents the true identiy
         of the client (ie: name of your company).'),
      '#default_value' => $config->get('shurly_gsb_client'),
    ];

    $form['shurly_restrictions']['shurly_gsb_apikey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('Add your API key.'),
      '#default_value' => $config->get('shurly_gsb_apikey'),
    ];

    $form['shurly_throttle'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Rate limiting'),
      '#tree' => TRUE,
      '#description' => $this->t("Limit requests by IP address. Leave blank for no rate limiting.<br /><strong>Note:</strong> Only roles with the 'Create short URLs' permission are listed here."),
    ];

    $saved = $config->get('shurly_throttle');
    $roles = array_filter(Role::loadMultiple(), fn(RoleInterface $role) => $role->hasPermission('create short URLs'));

    /**
     * @var string $rid
     * @var \Drupal\user\Entity\Role $role
     */
    foreach ($roles as $rid => $role) {
      $rate = $saved[$rid]['rate'] ?? NULL;
      $time = $saved[$rid]['time'] ?? NULL;

      $form['shurly_throttle'][$rid] = [
        '#type' => 'fieldset',
        '#title' => $role->label(),
        '#tree' => TRUE,
      ];
      $form['shurly_throttle'][$rid]['rate'] = [
        '#type' => 'textfield',
        '#size' => '3',
        '#prefix' => '<div class="container-inline">',
        '#field_suffix' => ' ' . $this->t('requests'),
        '#default_value' => $rate,
      ];
      $form['shurly_throttle'][$rid]['time'] = [
        '#type' => 'textfield',
        '#size' => '3',
        '#field_prefix' => $this->t('within'),
        '#field_suffix' => ' ' . $this->t('minutes'),
        '#default_value' => $time,
        '#suffix' => '</div>',
      ];
      $form['shurly_throttle'][$rid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#default_value' => $saved[$rid]['weight'] ?? 0,
        '#description' => $this->t("Order of this role when considering a user with multiple roles. A user's lightest role will take precedence."),
      ];

    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $custom_base_url = trim($form_state->getValue('shurly_base'));
    // Throw an error if base URL is set, and is not valid.
    if (!empty($custom_base_url) && !UrlHelper::isValid($custom_base_url, $absolute = TRUE)) {
      $form_state->setErrorByName('shurly_base', $this->t('The base URL is not valid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('shurly.settings');
    $form_state->cleanValues();

    foreach ($form_state->getValues() as $key => $value) {
      $config->set($key, $value);
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
