<?php

namespace Drupal\charts_highcharts_maps\Plugin\EntityReferenceSelection;

use Drupal\file\Plugin\EntityReferenceSelection\FileSelection;

/**
 * Provides a custom CSV file selection to only match files with csv mime type.
 *
 * @EntityReferenceSelection(
 *   id = "default:charts_highcharts_charts_json_file_entity_selection",
 *   label = @Translation("JSON File selection"),
 *   entity_types = {"file"},
 *   group = "default",
 *   weight = -99
 * )
 */
class JsonFileSelection extends FileSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $configuration = $this->getConfiguration();
    $query = parent::buildEntityQuery($match, $match_operator);
    // Ensure that the map data source is set before adding the mime type.
    if (empty($configuration['map_data_source_settings'])) {
      return $query;
    }
    $query->condition('filemime', 'application/json');
    return $query;
  }

}
