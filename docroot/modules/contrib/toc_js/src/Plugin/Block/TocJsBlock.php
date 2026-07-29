<?php

namespace Drupal\toc_js\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\toc_js\Service\TocJsService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Toc.js' block.
 *
 * @Block(
 *  id = "toc_js_block",
 *  category = @Translation("Toc js"),
 *  admin_label = @Translation("Toc.js block"),
 * )
 */
class TocJsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a TocJS block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The current route match service.
   * @param \Drupal\toc_js\Service\TocJsService $tocjsService
   *   The TocJs service.
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected TocJsService $tocjsService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $tocjs_service = $container->get('toc_js.service');
    return new static($configuration, $plugin_id, $plugin_definition, $tocjs_service);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return $this->tocjsService->defaultSettings() + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $this->tocjsService->getTocForm($form, $this->configuration, 'settings');
    return $form;
  }

  /**
   * Process block Toc.js configuration submit.
   *
   * @param array $form
   *   The form to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state to get the submitted values from.
   */
  protected function blockSubmitToc($form, FormStateInterface $form_state): void {
    $settings = array_keys($this->tocjsService->defaultSettings());
    foreach ($settings as $name) {
      $this->configuration[$name] = $form_state->getValue($this->getTocFormKey($name));
    }
  }

  /**
   * Get the form element key for a specific configuration item.
   *
   * @param string $name
   *   The name for the configuration item.
   *
   * @return string|array
   *   The form element key.
   */
  protected function getTocFormKey(string $name): string|array {
    return $name;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->blockSubmitToc($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $toc_settings = $this->configuration;
    return $this->tocjsService->buildToc($this->pluginId, $toc_settings);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), $this->tocjsService->getTocCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), $this->tocjsService->getTocCacheContexts());
  }

}
