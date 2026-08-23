<?php

namespace Drupal\views_date_format_sql\Plugin;

use Drupal\Component\Plugin\Definition\PluginDefinition;

/**
 * Provides a plugin definition with no provider.
 */
class ViewsDateFormatSqlPluginDefinition extends PluginDefinition {

  /**
   * {@inheritdoc}
   */
  protected $provider = NULL;

  /**
   * Initialization method.
   *
   * @param string $id
   *   The plugin id to use.
   * @param string $class
   *   The class to use.
   */
  public function __construct($id = NULL, $class = NULL) {
    $this->id = $id;
    $this->setClass($class);
  }

}
