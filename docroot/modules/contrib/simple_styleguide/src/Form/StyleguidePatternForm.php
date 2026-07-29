<?php

namespace Drupal\simple_styleguide\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Admin config form for adding styleguide patterns.
 *
 * @package Drupal\simple_styleguide\Form
 */
class StyleguidePatternForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function getListUrl() {
    return new Url('entity.styleguide_pattern.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $styleguide_pattern = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $styleguide_pattern->label(),
      '#description' => $this->t("Label for the Styleguide pattern."),
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#format' => 'full_html',
      '#rows' => 5,
      '#default_value' => ($styleguide_pattern->description['value'] ?? ''),
    ];

    $form['pattern'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pattern'),
      '#rows' => 15,
      '#default_value' => $styleguide_pattern->pattern,
      '#description' => $this->t('Paste in your raw html.'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $styleguide_pattern->id(),
      '#machine_name' => [
        'exists' => '\Drupal\simple_styleguide\Entity\StyleguidePattern::load',
      ],
      '#disabled' => !$styleguide_pattern->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $styleguide_pattern = $this->entity;

    // Add as highest ranked by weight to put it at the bottom of the list.
    $storage = $this->entityTypeManager->getStorage('styleguide_pattern');
    $ids = $storage->getQuery()->accessCheck(TRUE)->execute();
    if (!empty($ids)) {
      $custom_patterns = $storage->loadMultiple($ids);
    }

    // If no weight is set, then this is a new entry.
    if (!$styleguide_pattern->weight && !empty($custom_patterns)) {
      $maxWeight = NULL;
      foreach ($custom_patterns as $pattern) {
        if (!$maxWeight || $pattern->weight > $maxWeight) {
          $maxWeight = $pattern->weight;
        }
      }

      $styleguide_pattern->weight = $maxWeight + 1;
    }

    // Save pattern entity.
    $status = $styleguide_pattern->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Styleguide pattern.', [
          '%label' => $styleguide_pattern->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Styleguide pattern.', [
          '%label' => $styleguide_pattern->label(),
        ]));
    }

    $form_state->setRedirectUrl($this->getListUrl());
  }

}
