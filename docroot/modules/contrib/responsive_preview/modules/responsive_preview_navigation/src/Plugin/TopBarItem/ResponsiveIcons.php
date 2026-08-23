<?php

declare(strict_types=1);

namespace Drupal\responsive_preview_navigation\Plugin\TopBarItem;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\navigation\Attribute\TopBarItem;
use Drupal\navigation\TopBarItemBase;
use Drupal\navigation\TopBarRegion;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\responsive_preview\ResponsivePreview;

/**
 * Provides the Responsive Icons top bar item.
 */
#[TopBarItem(
  id: 'responsive_icons',
  region: TopBarRegion::Tools,
  label: new TranslatableMarkup('Responsive Icons'),
)]
class ResponsiveIcons extends TopBarItemBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new ResponsiveIcons instance.
   *
   * @param array $configuration
   *   A configuration array containing plugin instance information.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\responsive_preview\ResponsivePreview $responsivePreview
   *   The ResponsivePreview.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected ResponsivePreview $responsivePreview,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('responsive_preview'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [
      '#cache' => [
        'contexts' => [
          'user.permissions',
          'route',
        ],
      ],
    ];
    // Check if the user has the required permission.
    if (!$this->currentUser->hasPermission('access responsive preview')) {
      return $build;
    }

    try {
      $url = $this->responsivePreview->getPreviewUrl();
    }
    catch (\InvalidArgumentException $e) {
      return $build;
    }

    if ($url) {
      return $this->buildResponsivePreviewNavigation($url);
    }

    return $build;
  }

  /**
   * Builds the responsive preview navigation render array.
   *
   * @param string $url
   *   The responsive preview URL.
   *
   * @return array
   *   A render array for the responsive preview navigation.
   */
  protected function buildResponsivePreviewNavigation(string $url): array {
    return [
      '#theme' => 'responsive_preview_navigation',
      '#cache' => [
        'contexts' => [
          'user.permissions',
          'route',
        ],
        'tags' => ['config:node_type_list'],
      ],
      '#attached' => [
        'library' => [
          'responsive_preview/drupal.responsive-preview',
          'responsive_preview_navigation/drupal.responsive-preview-navigation',
        ],
        'drupalSettings' => [
          'responsive_preview' => [
            'url' => ltrim($url, '/'),
          ],
        ],
      ],
      '#items' => $this->getResponsivePreviewItems(),
      '#attributes' => [
        'id' => 'responsive-preview-toolbar-tab',
        'class' => ['responsive-preview-navigation-bar'],
      ],
    ];
  }

  /**
   * Defines the responsive preview items.
   *
   * @return array
   *   An array of responsive preview items.
   */
  protected function getResponsivePreviewItems(): array {
    return [
      [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Desktop'),
        '#attributes' => [
          'data-responsive-preview-name' => 'desktop',
          'data-responsive-preview-width' => 1280,
          'data-responsive-preview-height' => 768,
          'data-responsive-preview-dppx' => 1.0,
          'data-responsive-preview-orientation' => 'landscape',
          'class' => [
            'responsive-preview-device',
            'responsive-preview-icon',
            'device-icon-desktop',
          ],
        ],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Mobile'),
        '#attributes' => [
          'data-responsive-preview-name' => 'mobile',
          'data-responsive-preview-width' => 768,
          'data-responsive-preview-height' => 1280,
          'data-responsive-preview-dppx' => 2,
          'data-responsive-preview-orientation' => 'portrait',
          'class' => [
            'responsive-preview-device',
            'responsive-preview-icon',
            'device-icon-mobile',
          ],
        ],
      ],
    ];
  }

}
