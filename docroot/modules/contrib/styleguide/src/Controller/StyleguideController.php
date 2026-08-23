<?php

namespace Drupal\styleguide\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Link;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\styleguide\StyleguidePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The Styleguide controller.
 */
class StyleguideController extends ControllerBase {

  public function __construct(
    protected ThemeExtensionList $themeExtensionList,
    protected StyleguidePluginManager $styleguideManager,
    protected ThemeManagerInterface $themeManager,
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.theme'),
      $container->get('plugin.manager.styleguide'),
      $container->get('theme.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * Build styleguide page.
   *
   * @return array
   *   Renderable array of styleguide items.
   */
  public function page() {
    // Get active theme.
    $active_theme = $this->themeManager->getActiveTheme()->getName();
    $themes = $this->themeExtensionList->reset()->getList();

    // Get theme data.
    $theme_info = $themes[$active_theme]->info;

    $items = [];
    foreach ($this->styleguideManager->getDefinitions() as $plugin_id => $plugin_definition) {
      $plugin = $this->styleguideManager->createInstance($plugin_id, ['of' => 'configuration values']);
      $items = array_merge($items, $plugin->items());
    }

    $this->moduleHandler()->alter('styleguide', $items);
    $this->themeManager->alter('styleguide', $items);

    $groups = [];
    foreach ($items as $key => $item) {
      if (!isset($item['group'])) {
        $item['group'] = $this->t('Common');
      }
      else {
        $item['group'] = $this->t('@group', ['@group' => $item['group']]);
      }
      $item['title'] = $this->t('@title', ['@title' => $item['title']]);
      $groups[$item['group']->__toString()][$key] = $item;
    }

    ksort($groups);
    // Create a navigation header.
    $header = $head = $content = [];
    // Process the elements, by group.
    foreach ($groups as $group => $elements) {
      foreach ($elements as $key => $item) {
        $id = Html::getUniqueId($key);
        $display = [];
        // Output a standard HTML tag.
        if (isset($item['tag']) && isset($item['content'])) {
          $tag = [
            '#type' => 'html_tag',
            '#tag' => $item['tag'],
            '#value' => $item['content'],
          ];
          if (!empty($item['attributes'])) {
            $tag['#attributes'] = $item['attributes'];
          }
          $display[] = $tag;
        }
        // Support a renderable array for content.
        elseif (isset($item['content']) && is_array($item['content'])) {
          $display[] = $item['content'];
        }
        // Just print the provided content.
        elseif (isset($item['content'])) {
          $display[] = ['#markup' => $item['content']];
        }
        // Add the content.
        $content[] = [
          '#theme' => 'styleguide_item',
          '#key' => $id,
          '#item' => $item,
          '#content' => $display,
        ];
        // Prepare the header link.
        $uri = $this->requestStack->getCurrentRequest()->getUri();
        $url = Url::fromUri($uri, ['fragment' => $id]);
        $link = Link::fromTextAndUrl($item['title'], $url);
        $header[$group][] = $link->toRenderable();
      }
      $head[] = [
        '#theme' => 'item_list',
        '#items' => $header[$group],
        '#title' => $group,
      ];
    }

    return [
      '#title' => 'Style guide',
      'header' => [
        '#theme' => 'styleguide_header',
        '#theme_info' => $theme_info,
      ],
      'navigation' => [
        '#theme' => 'styleguide_links',
        '#items' => $head,
      ],
      'content' => [
        '#theme' => 'styleguide_content',
        '#content' => $content,
      ],
      '#attached' => [
        'library' => [
          'styleguide/styleguide_css',
        ],
      ],
    ];
  }

}
