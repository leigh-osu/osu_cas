<?php

namespace Drupal\rules\Ui;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\Plugin\Factory\ContainerFactory;

/**
 * Plugin manager for Rules Ui instances.
 *
 * Rules UIs are primarily defined in *.rules_ui.yml files. Usually, there is
 * no need to specify a 'class' as there is a suiting default handler class in
 * place. However, if done see the class must implement
 * \Drupal\rules\Ui\RulesUiHandlerInterface.
 *
 * @see \Drupal\rules\Ui\RulesUiHandlerInterface
 */
class RulesUiManager extends DefaultPluginManager implements RulesUiManagerInterface {

  /**
   * Constructs a RulesUiManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    $this->factory = new ContainerFactory($this, RulesUiHandlerInterface::class);
    $this->moduleHandler = $module_handler;
    $this->alterInfo('rules_ui_info');
    $this->setCacheBackend($cache_backend, 'rules_ui_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $yaml_discovery = new YamlDiscovery('rules_ui', $this->moduleHandler->getModuleDirectories());
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($yaml_discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    $definition = new RulesUiDefinition($definition);
    $definition->validate();
  }

}
