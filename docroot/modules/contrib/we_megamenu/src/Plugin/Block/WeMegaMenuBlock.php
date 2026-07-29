<?php

namespace Drupal\we_megamenu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\we_megamenu\WeMegaMenuBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides a 'Mega Menu' Block.
 *
 * @Block(
 *   id = "we_megamenu_block",
 *   admin_label = @Translation("Mega Menu"),
 *   category = @Translation("Drupal 8 Mega Menu"),
 *   deriver = "Drupal\we_megamenu\Plugin\Derivative\WeMegaMenuBlock",
 * )
 */
class WeMegaMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the WeMegaMenuAdminController.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#theme' => 'we_megamenu_frontend',
      '#menu_name' => $this->getDerivativeId(),
      '#blocks' => WeMegaMenuBuilder::getAllBlocks(),
      '#block_theme' => $this->configFactory->get('system.theme')->get('default'),
      '#attached' => [
        'library' => [
          'we_megamenu/form.we-mega-menu-frontend',
        ],
      ],
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $menu_name = $this->getDerivativeId();
    $ids[] = 'config:system.menu.' . $menu_name;
    $ids[] = 'we_mega_menu.block.' . $menu_name;
    return Cache::mergeTags(parent::getCacheTags(), $ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $menu_name = $this->getDerivativeId();
    $id_menu = 'route.menu_active_trails:' . $menu_name;
    $ids = [$id_menu];
    return Cache::mergeContexts(parent::getCacheContexts(), $ids);
  }

}
