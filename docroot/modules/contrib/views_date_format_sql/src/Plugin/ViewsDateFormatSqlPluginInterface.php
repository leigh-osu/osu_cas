<?php

namespace Drupal\views_date_format_sql\Plugin;

use Drupal\Component\Plugin\DependentPluginInterface;

/**
 * Identifies views plugins to ignore provider dependency.
 *
 * Returns a component plugin with provider set to NULL so that dependencies
 * are calculated by the views plugin class instance.
 */
interface ViewsDateFormatSqlPluginInterface extends DependentPluginInterface {

  /**
   * Returns a separate plugin definition class.
   *
   * @return \Drupal\views_date_format_sql\Plugin\ViewsDateFormatSqlPluginDefinition
   *   A plugin definition class rather than the array.
   */
  public function getPluginDefinition();

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies();

}
