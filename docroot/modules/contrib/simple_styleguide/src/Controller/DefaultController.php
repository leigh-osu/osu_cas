<?php

namespace Drupal\simple_styleguide\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Primary controller styleguide and config pages.
 *
 * @package Drupal\simple_styleguide\Controller
 */
class DefaultController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * Primary Page.
   *
   * @return array
   *   Returns markup array.
   */
  public function index() {
    $config = $this->config('simple_styleguide.styleguidesettings');
    $config_colors = $config->get('default_colors');
    $config_patterns = $config->get('default_patterns');

    // Selected patterns.
    $default_patterns = [];
    if ($config_patterns) {
      foreach ($config_patterns as $key => $value) {
        if ($value) {
          $default_patterns[] = $key;
        }
      }
    }

    // Custom colors.
    $default_colors = [];
    if ($config_colors) {
      foreach ($config_colors as $key => $value) {
        $color_split = explode('|', $value);

        if (!empty($color_split[0])) {
          $default_colors[$key]['hex'] = $color_split[0];

          $rgb = array_map('hexdec', str_split(ltrim($color_split[0], '#'), 2));
          $rgb_value = implode(',', $rgb);
          $default_colors[$key]['rgb'] = $rgb_value;
        }

        if (!empty($color_split[1])) {
          $default_colors[$key]['class'] = $color_split[1];
        }

        if (!empty($color_split[2])) {
          $default_colors[$key]['description'] = $color_split[2];
        }
      }
    }

    // Custom patterns.
    $custom_patterns = [];
    $storage = $this->entityTypeManager->getStorage('styleguide_pattern');
    $ids = $storage->getQuery()->accessCheck(TRUE)->execute();
    if (!empty($ids)) {
      $patterns = $storage->loadMultiple($ids);

      foreach ($patterns as $pattern) {
        $pattern->template = [
          '#theme' => 'simple_styleguide_pattern',
          '#pattern_id' => $pattern->id(),
          '#pattern' => $pattern->pattern,
        ];

        $custom_patterns[] = $pattern;
      }
    }

    // Sort patterns by weight.
    usort($custom_patterns, function ($a, $b) {
      return $a->get('weight') <=> $b->get('weight');
    });

    $form = $this->formBuilder->getForm('Drupal\simple_styleguide\Form\StyleguideExamples');

    return [
      '#theme' => 'simple_styleguide',
      '#default_patterns' => $default_patterns,
      '#default_colors' => $default_colors,
      '#custom_patterns' => $custom_patterns,
      '#form' => $form,
    ];
  }

}
