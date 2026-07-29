<?php

namespace Drupal\bootstrap_layout_builder\Plugin\BootstrapStyles\Style;

use Drupal\bootstrap_styles\Style\StylePluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Style ItemsAlignment class.
 *
 * @package Drupal\bootstrap_layout_builder\Plugin\Style
 *
 * @Style(
 *   id = "items_alignment",
 *   title = @Translation("Items Alignment"),
 *   group_id = "positioning",
 *   weight = 0
 * )
 */
class ItemsAlignment extends StylePluginBase {

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->config();

    $form['positioning']['items_alignment'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('items_alignment'),
      '#title' => $this->t('Items Alignment'),
      '#cols' => 60,
      '#rows' => 5,
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->config()
      ->set('items_alignment', $form_state->getValue('items_alignment'))
      ->save();
  }

  /**
   * {@inheritDoc}
   */
  public function buildStyleFormElements(array &$form, FormStateInterface $form_state, $storage) {
    $form['items_alignment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Items Alignment'),
      '#options' => $this->getStyleOptions('items_alignment'),
      '#default_value' => $storage['items_alignment']['class'] ?? NULL,
      '#validated' => TRUE,
      '#attributes' => [
        'class' => ['field-items-alignment', 'bs_input-boxes'],
      ],
    ];

    // Add icons to the container types.
    foreach ($form['items_alignment']['#options'] as $key => $value) {
      $form['items_alignment']['#options'][$key] = '<span class="input-icon ' . $key . '"></span>' . $value;
    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitStyleFormElements(array $group_elements) {
    return [
      'items_alignment' => [
        'class' => $group_elements['items_alignment'],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function build(array $build, array $storage, $theme_wrapper = NULL) {
    $classes = [];
    if (isset($storage['items_alignment']['class'])) {
      $classes[] = $storage['items_alignment']['class'];
    }

    // Add the classes to the build.
    $build = $this->addClassesToBuild($build, $classes);

    // Attach blb-classes to the build.
    $build['#attached']['library'][] = 'bootstrap_layout_builder/plugin.items_alignment.build';

    return $build;
  }

}
