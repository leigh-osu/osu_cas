<?php

namespace Drupal\styleguide\Plugin\Styleguide;

use Drupal\styleguide\Plugin\StyleguidePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders all found layouts from core Layout Discovery module.
 *
 * @Plugin(
 *   id = "layout_styleguide",
 *   label = @Translation("Layouts Styleguide elements"),
 *   provider = "layout_builder",
 * )
 */
class LayoutStyleguide extends StyleguidePluginBase {

  /**
   * The layout plugin manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->layoutPluginManager = $container->get('plugin.manager.core.layout');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function items() {
    $items = [];

    /** @var \Drupal\Core\Layout\LayoutDefinition[] $layouts */
    $layouts = $this->layoutPluginManager->getFilteredDefinitions('layout_builder', []);

    foreach ($layouts as $layout => $definition) {
      $group = (string) $definition->getCategory();
      if (!isset($items[$group])) {
        $items[$group] = [
          'title' => $definition->getCategory(),
          'content' => [],
          'group' => $this->t('Layouts'),
        ];
      }

      $details = [];

      if ($property = $definition->id()) {
        $details[] = $this->t('ID: %property', [
          '%property' => $property,
        ]);
      }
      if ($property = $definition->getDescription()) {
        $details[] = $this->t('Description: %property', [
          '%property' => $property,
        ]);
      }
      if ($property = $definition->getProvider()) {
        $details[] = $this->t('Provider: %property', [
          '%property' => $property,
        ]);
      }
      if ($property = $definition->getDefaultRegion()) {
        $details[] = $this->t('Default region: %property', [
          '%property' => $property,
        ]);
      }
      if ($property = $definition->getLibrary()) {
        $details[] = $this->t('Library: %property', [
          '%property' => $property,
        ]);
      }

      $build = [];
      if ($region_labels = $definition->getRegionLabels()) {
        $regions = [];
        foreach ($region_labels as $id => $label) {
          $regions[$id] = [
            '#type' => 'inline_template',
            '#template' => '<span class="block-region demo-block">{{ label }} ({{ id }})</span>',
            '#context' => [
              'id' => $id,
              'label' => $label,
            ],
          ];
        }
        $layoutInstance = $this->layoutPluginManager->createInstance($layout);
        $build = $layoutInstance->build($regions);
      }

      $items[$group]['content'][$layout] = [
        '#type' => 'details',
        '#title' => $definition->getLabel(),
        '#open' => FALSE,
        '#description' => [
          'info' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => 'styleguide__layout-info',
            ],
            'icon' => $definition->getIcon(60, 80, 1, 3),
            'details' => [
              '#theme' => 'item_list',
              '#items' => $details,
            ],
          ],
          'body' => [
            'build' => $build,
          ],
        ],
      ];
    }

    return $items;
  }

}
