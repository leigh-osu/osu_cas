<?php

namespace Drupal\styleguide\Plugin\Styleguide;

use Drupal\styleguide\Plugin\StyleguidePluginBase;

/**
 * Views Styleguide items implementation.
 *
 * @Plugin(
 *   id = "filter_styleguide",
 *   label = @Translation("Filter Styleguide elements"),
 *   provider = "filter",
 * )
 */
class FilterStyleguide extends StyleguidePluginBase {

  /**
   * {@inheritdoc}
   */
  public function items() {
    $items = [];

    $items['text_format'] = [
      'title' => $this->t('Text format'),
      'content' => $this->formBuilder->getForm('Drupal\styleguide\Form\StyleguideForm', ['text_format']),
      'group' => $this->t('System'),
    ];
    $items['filter_tips'] = [
      'title' => $this->t('Filter tips, short'),
      'content' => [
        '#theme' => 'filter_tips',
        '#tips' => _filter_tips(-1, FALSE),
        '#long' => FALSE,
      ],
      'group' => $this->t('System'),
    ];
    $items['filter_tips_long'] = [
      'title' => $this->t('Filter tips, long'),
      'content' => [
        '#theme' => 'filter_tips',
        '#tips' => _filter_tips(-1, TRUE),
        '#long' => TRUE,
      ],
      'group' => $this->t('System'),
    ];

    return $items;
  }

}
