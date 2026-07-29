<?php

declare(strict_types=1);

namespace Drupal\layout_builder_reorder;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;

/**
 * Add Pre-render trusted callback for Layout Builder Reorder.
 */
class SectionRearrangeRender implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRender'];
  }

  /**
   * Pre-render callback for layout builder.
   */
  public static function preRender(array $elements): array {
    if (!isset($elements['layout_builder']) || !\is_array($elements['layout_builder'])) {
      return $elements;
    }

    // Get the sections that can be reordered through layout builder.
    $sections = \array_filter(
      $elements['layout_builder'],
      static function ($section, $key) {
        return \is_numeric($key) && (isset($section['configure'], $section['configure']['#url'])) && $section['configure']['#url'] instanceof Url;
      },
      \ARRAY_FILTER_USE_BOTH
    );

    // Track the current index and get the number of sections.
    $index = 0;
    $number_of_sections = \count($sections);

    foreach ($sections as $key => $section) {
      ++$index;

      // Add rearrange buttons.
      // @phpstan-ignore-next-line
      $params = $section['configure']['#url']->getRouteParameters();

      $route_parameters = [
        'section_storage_type' => $params['section_storage_type'],
        'section_storage' => $params['section_storage'],
        'delta' => $params['delta'],
      ];

      $options = [
        'attributes' => [
          'class' => [
            'use-ajax',
            'layout-builder__link',
            'layout-builder__link--rearrange',
          ],
        ],
      ];

      if ($index > 1) {
        // Add move up link.
        $move_up_url = Url::fromRoute(
          'layout_builder_reorder.move_section',
          // @phpstan-ignore-next-line
          \array_merge($route_parameters, ['new_delta' => $params['delta'] - 1]),
          \array_merge_recursive($options, [
            'attributes' => [
              'class' => [
                'layout-builder__link--rearrange--up',
              ],
            ],
          ])
        );
        $sections[$key]['rearrange_up'] = [
          '#type' => 'link',
          '#title' => \t('Move up'),
          '#url' => $move_up_url,
          '#access' => $move_up_url->access(),
        ];
      }

      // Down link.
      if ($index < $number_of_sections) {
        // Add move down link.
        $move_down_url = Url::fromRoute(
          'layout_builder_reorder.move_section',
          // @phpstan-ignore-next-line
          \array_merge($route_parameters, ['new_delta' => $params['delta'] + 1]),
          \array_merge_recursive($options, [
            'attributes' => [
              'class' => [
                'layout-builder__link--rearrange--down',
              ],
            ],
          ])
        );
        $sections[$key]['rearrange_down'] = [
          '#type' => 'link',
          '#title' => \t('Move down'),
          '#url' => $move_down_url,
          '#access' => $move_down_url->access(),
        ];
      }

      // Push the updated section onto the layout builder page.
      $elements['layout_builder'][$key] = $sections[$key];
    }

    return $elements;
  }

}
