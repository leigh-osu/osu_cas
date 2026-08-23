<?php

namespace Drupal\views_migration\Plugin\migrate\views\filter\d7;

/**
 * The Views Migrate plugin for "search_api_index_filters" Filter Handlers.
 *
 * @MigrateViewsFilter(
 *   id = "search_api_index_filters",
 *   tables = {
 *     "search_api_index_all_content"
 *   },
 *   core = {7},
 * )
 */
class SearchAPIIndexFilters extends DefaultFilter {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    parent::alterHandlerConfig($handler_config);
    if (isset($this->viewsData[$handler_config['table']])) {
      if (isset($this->viewsData[$handler_config['table']][$handler_config['field']])) {
        if (isset($this->viewsData[$handler_config['table']][$handler_config['field']]['filter'])) {
          $filter = $this->viewsData[$handler_config['table']][$handler_config['field']]['filter'];
          switch ($filter['id']) {
            case 'search_api_boolean':
              if ($handler_config['operator'] == '<>') {
                $handler_config['operator'] = '!=';
                if (is_array($handler_config['value'])) {
                  $handler_config['value'] = reset($handler_config['value']);
                }
              }
              elseif ($handler_config['operator'] == '=') {
                $handler_config['operator'] = '=';
                if (is_array($handler_config['value'])) {
                  $handler_config['value'] = reset($handler_config['value']);
                }
              }
              elseif ($handler_config['operator'] == 'empty') {
                $handler_config['operator'] = '=';
                if (is_array($handler_config['value'])) {
                  $handler_config['value'] = 0;
                }
              }
              elseif ($handler_config['operator'] == 'not empty') {
                $handler_config['operator'] = '=';
                if (is_array($handler_config['value'])) {
                  $handler_config['value'] = 1;
                }
              }
              break;

            default:
              // code...
              break;
          }
        }
      }
    }
  }

}
