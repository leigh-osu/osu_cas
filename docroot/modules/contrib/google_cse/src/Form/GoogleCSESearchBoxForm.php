<?php

namespace Drupal\google_cse\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\google_cse\Plugin\Search\GoogleSearch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form builder for the searchbox forms.
 *
 * @package Drupal\google_cse\Form
 */
class GoogleCSESearchBoxForm extends FormBase {

  /**
   * RequestStack object for getting requests.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * GoogleCSESearchBoxForm constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request object.
   */
  public function __construct(RequestStack $requestStack) {
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'google_cse_search_box_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $form_state->getValue('google_search_settings');
    $form['#method'] = 'get';
    $form['cx'] = [
      '#type' => 'hidden',
      '#value' => $settings['cx'],
    ];
    $query_key = $settings['query_key'] ?? GoogleSearch::$defaultQueryKey;
    if ($settings['results_display'] === 'google') {
      $form['#action'] = 'https://cse.google.com/cse';
      // If the results are to be displayed on this site, use the site query
      // parameter. If on Google, use 'q'.
      $query_key = 'q';
    }
    $form[$query_key] = [
      '#type' => 'textfield',
      '#id' => 'google-cse-query',
      '#default_value' => $this->requestStack->getCurrentRequest()->query->has($query_key) ? $this->requestStack->getCurrentRequest()->query->get($query_key) : '',
    ];
    $form['sa'] = [
      '#type' => 'submit',
      '#id' => 'google-cse-submit',
      '#value' => $this->t('Search'),
    ];
    $form[$query_key]['#size'] = intval($settings['results_searchbox_width']);
    $form[$query_key]['#title'] = $this->t('Enter your keywords');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This is left blank intentionally.
  }

}
