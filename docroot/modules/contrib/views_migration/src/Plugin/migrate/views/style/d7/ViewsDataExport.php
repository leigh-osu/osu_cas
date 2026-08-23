<?php

namespace Drupal\views_migration\Plugin\migrate\views\style\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginPluginBase;

/**
 * The default Migrate Views Style plugin.
 *
 * This plugin is used to prepare the Views `style` display options for
 * migration when no other migrate plugin exists for the current style plugin.
 *
 * @MigrateViewsStyle(
 *   id = "data_export",
 *   plugin_ids = {
 *    "data_export"
 *   },
 *   core = {7},
 * )
 */
class ViewsDataExport extends MigrateViewsPluginPluginBase {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    $display_options['style']['type'] = $display_options['style_plugin'];
    if (isset($display_options['style_options'])) {
      $d7StyleOp = $display_options['style_options'];
      $styleOptions['formats'] = [
        'csv' => 'csv',
      ];
      $styleOptions['csv_settings'] = [
        'delimiter' => ',',
        'enclosure' => '"',
        'escape_char' => '\\',
        'strip_tags' => TRUE,
        'trim' => TRUE,
        'utf8_bom' => 1,
        'use_serializer_encode_only' => TRUE,
      ];
      if (isset($d7StyleOp['separator'])) {
        $styleOptions['csv_settings']['delimiter'] = $d7StyleOp['separator'];
      }
      if (isset($d7StyleOp['trim'])) {
        $styleOptions['csv_settings']['trim'] = $d7StyleOp['trim'];
      }
      $display_options['style']['options'] = $styleOptions;
    }
  }

}
