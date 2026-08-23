<?php

namespace Drupal\bootstrap_layout_builder\Plugin\BootstrapStyles\StylesGroup;

use Drupal\bootstrap_styles\StylesGroup\StylesGroupPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Style group Positioning class.
 *
 * @package Drupal\bootstrap_layout_builder\Plugin\StylesGroup
 *
 * @StylesGroup(
 *   id = "positioning",
 *   title = @Translation("Positioning"),
 *   weight = 6,
 *   icon = "bootstrap_layout_builder/images/plugins/positioning-icon.svg"
 * )
 */
class Positioning extends StylesGroupPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['positioning'] = [
      '#type' => 'details',
      '#title' => $this->t('Positioning'),
      '#open' => FALSE,
    ];
    return $form;
  }

}
