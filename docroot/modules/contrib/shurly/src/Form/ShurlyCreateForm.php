<?php

namespace Drupal\shurly\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * ShurlyCreateForm.
 */
class ShurlyCreateForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shurly_create_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['wrapper'] = [
      '#prefix' => '<div id="shurly-create">',
      '#suffix' => '</div>',
    ];

    $form['wrapper']['long_url'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('long_url'),
      '#maxlength' => 2048,
      '#placeholder' => $this->t('Shorten your link'),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['wrapper']['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom URL'),
      '#access' => \Drupal::currentUser()->hasPermission('enter custom URLs'),
    ];

    $form['wrapper']['options']['short_url'] = [
      '#type' => 'textfield',
      '#default_value' => $form_state->getValue('short_url'),
      '#size' => 6,
      '#field_prefix' => _shurly_get_shurly_base() . '/',
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['wrapper']['actions'] = [
      '#type' => 'actions',
    ];

    $form['wrapper']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Shorten'),
      '#ajax' => [
        'callback' => '::submitAjaxCall',
        'wrapper' => 'shurly-create',
        'event' => 'click',
      ],
    ];

    $form['wrapper']['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Shorten another'),
      '#access' => FALSE,
      '#ajax' => [
        'callback' => '::resetAjaxCall',
        'wrapper' => 'shurly-create',
        'event' => 'click',
      ],
    ];
    $form['#attached']['library'][] = 'shurly/clipboardjs';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $rate_limit = shurly_rate_limit_allowed();
    if (!$rate_limit['allowed']) {
      $form_state->setError('', $this->t('Rate limit exceeded. You are limited to @rate requests per @time minute period.', [
        '@rate' => $rate_limit['rate'],
        '@time' => $rate_limit['time'],
      ]));
      return;
    }

    $form_state->setValue('long_url', trim($form_state->getValue('long_url')));
    $form_state->setValue('short_url', trim($form_state->getValue('short_url')));

    $vals = $form_state->getValues();

    $long_url_name = 'wrapper][long_url';
    $short_url_name = 'wrapper][options][short_url';

    if ($vals['long_url']) {
      // Check that they've entered a URL.
      if (!preg_match('/^https?:\/\//', $vals['long_url'])) {
        $form_state->setErrorByName($long_url_name, $this->t('Please enter a web URL.'));
      }
      elseif (!shurly_validate_long($vals['long_url'])) {
        $form_state->setErrorByName($long_url_name, $this->t('Invalid URL.'));
      }
    }

    if ($vals['short_url']) {
      // A custom short URL has been entered.
      $form_state->setValue('custom', [TRUE]);

      if (!shurly_validate_custom($vals['short_url'])) {
        $form_state->setErrorByName($short_url_name, $this->t('Short URL contains invalid characters.'));
      }
      elseif (shurly_url_exists($vals['short_url'], $vals['long_url'])) {
        $form_state->setErrorByName($short_url_name, $this->t('This short URL has already been used.'));
      }
      elseif (_surl($vals['short_url'], ['absolute' => TRUE]) == $vals['long_url'] || _surl($vals['short_url'], [
        'absolute' => TRUE,
        'base_url' => _shurly_get_shurly_base(),
      ]) == $vals['long_url']) {
        // Check that link isn't to itself (creating infinite loop)
        // problem - http vs https.
        $form_state->setErrorByName($short_url_name, $this->t('You cannot create links to themselves'));
      }
      elseif (!shurly_path_available($vals['short_url'])) {
        $form_state->setErrorByName($short_url_name, $this->t('This custom URL is reserved. Please choose another.'));
      }
    }
    else {
      // Custom short URL field is empty.
      $form_state->setValue('custom', TRUE);
      if ($exist = shurly_get_latest_short($vals['long_url'], \Drupal::currentUser()->id())) {
        $short = $exist;
        // Flag as URL Exists so that it displays but isn't saved to the db.
        $form_state->setValue('url_exists', TRUE);
      }
      else {
        $short = shurly_next_url();
      }
      $form_state->setValue('short_url', $short);
      $form_state->setStorage(['shurly' => ['short_url' => $short]]);
    }

    // Check that the destination URL is "safe".
    if (\Drupal::config('shurly.settings')->get('shurly_gsb')) {
      $gsb = shurly_gsb($vals['long_url']);

      if ($gsb) {
        $form_state->setErrorByName($long_url_name, $this->t('This URL is either phishing, malware, or both.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // AJAX submit only.
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function submitAjaxCall(array &$form, FormStateInterface $form_state) {
    $errors = $form_state->getErrors();
    $long_url = $form_state->getValue('long_url');
    $short_url = $form_state->getValue('short_url');

    if (count($errors)) {
      $form['wrapper']['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -999,
      ];

      if (isset($errors['wrapper][options][short_url'])) {
        $form['wrapper']['options']['#open'] = TRUE;
      }

      return $form['wrapper'];
    }

    $storage = [
      'shurly' => [
        'long_url' => $long_url,
        'short_url' => $short_url,
        'final_url' => urldecode(_shurly_get_shurly_base() . '/' . $short_url),
      ],
    ];

    $custom = $form_state->setValue('custom', [$form_state->getValue('custom')]);

    if (empty($form_state->getValue('url_exists'))) {
      shurly_save_url($long_url, $short_url, NULL, $custom);
    }

    $form['wrapper']['final_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Shortened URL'),
      '#title_display' => 'invisible',
      '#value' => $storage['shurly']['final_url'],
      '#disabled' => TRUE,
      '#attributes' => [
        'readonly' => 'readonly',
        'onclick' => 'this.select()',
      ],
    ];

    $form['wrapper']['long_url']['#access'] = FALSE;
    $form['wrapper']['options']['#access'] = FALSE;
    $form['wrapper']['actions']['submit']['#access'] = FALSE;
    $form['wrapper']['actions']['reset']['#access'] = TRUE;

    return $form['wrapper'];
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function resetAjaxCall(array &$form, FormStateInterface $form_state) {
    $form['wrapper']['final_url']['#access'] = FALSE;
    $form['wrapper']['actions']['reset']['#access'] = FALSE;
    $form['wrapper']['long_url']['#access'] = TRUE;
    $form['wrapper']['options']['#access'] = TRUE;
    $form['wrapper']['actions']['submit']['#access'] = TRUE;

    return $form['wrapper'];
  }

}
