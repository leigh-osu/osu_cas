<?php

declare(strict_types=1);

namespace Drupal\entityreference_filter\Hook;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for entityreference_filter.
 */
final class EntityReferenceFilterHooks {

  use StringTranslationTrait;

  /**
   * The field storage config entity storage.
   */
  protected EntityStorageInterface $fieldStorageConfigStorage;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DiscoveryInterface $viewsFilterManager,
  ) {
    $this->fieldStorageConfigStorage = $entityTypeManager->getStorage('field_storage_config');
  }

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    // Maps each entity base/data table to its entity type and id key.
    $entity_tables = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $definition) {
      if (!($definition instanceof ContentEntityTypeInterface)) {
        continue;
      }
      $id_key = $definition->getKey('id');
      if (!$id_key) {
        continue;
      }
      foreach ([$definition->getDataTable(), $definition->getBaseTable()] as $table) {
        if ($table) {
          $entity_tables[$table] = [
            'entity_type' => $entity_type_id,
            'id_key' => $id_key,
          ];
        }
      }
    }

    // Returns the entity type whose id column $field is on table $table, else
    // NULL.
    $id_column_entity = static fn(string $table, string $field): ?string =>
      (isset($entity_tables[$table]) && $entity_tables[$table]['id_key'] === $field)
        ? $entity_tables[$table]['entity_type']
        : NULL;

    $storage_ids = [];
    foreach ($data as $table_name => $table_info) {
      foreach ($table_info as $field_name => $field_info) {
        if ($id_column_entity($table_name, $field_name) === NULL
          && !empty($field_info['filter']['field_name'])
          && !empty($field_info['filter']['entity_type']) && mb_substr($field_name, -10) === '_target_id') {
          $storage_ids[] = $field_info['filter']['entity_type'] . '.' . $field_info['filter']['field_name'];
        }
        // Search API indexed fields keep their source field under 'field'.
        elseif (($field_info['field']['id'] ?? NULL) === 'search_api_field'
          && !empty($field_info['field']['entity_type'])
          && !empty($field_info['field']['field_name'])) {
          $storage_ids[] = $field_info['field']['entity_type'] . '.' . $field_info['field']['field_name'];
        }
      }
    }

    $field_storage_configs = $storage_ids ? $this->fieldStorageConfigStorage->loadMultiple(array_unique($storage_ids)) : [];

    foreach ($data as $table_name => $table_info) {
      foreach ($table_info as $field_name => $field_info) {
        $base_table = NULL;
        $target_entity_type = NULL;

        // The entity's own id column on its base/data table.
        $own_entity = $id_column_entity($table_name, $field_name);
        if ($own_entity !== NULL) {
          $target_entity_type = $own_entity;
        }
        // Other entity reference fields (ending in _target_id).
        elseif (!empty($field_info['filter']['field_name']) && !empty($field_info['filter']['entity_type']) && mb_substr($field_name, -10) === '_target_id') {
          $field_storage_config = $field_storage_configs[$field_info['filter']['entity_type'] . '.' . $field_info['filter']['field_name']] ?? NULL;
          if ($field_storage_config !== NULL) {
            $target_entity_type = $field_storage_config->getSetting('target_type');
          }
        }
        // Entity id columns exposed on another table via a Views relationship:
        // the relationship's base table + base field identify the entity whose
        // ids this column holds.
        elseif (isset($field_info['relationship']['base'], $field_info['relationship']['base field'])) {
          $target_entity_type = $id_column_entity(
            $field_info['relationship']['base'],
            $field_info['relationship']['base field'],
          );
        }
        // Search API indexed fields: the 'field' handler keeps the datasource
        // entity and the indexed field name. The target is either that entity
        // (when its own id is indexed, e.g. nid) or the reference field's
        // target type (e.g. field_tags -> taxonomy_term).
        elseif (($field_info['field']['id'] ?? NULL) === 'search_api_field'
          && !empty($field_info['field']['entity_type'])
          && !empty($field_info['field']['field_name'])
        ) {
          $source_entity = $field_info['field']['entity_type'];
          $source_field = $field_info['field']['field_name'];
          $source_definition = $this->entityTypeManager->getDefinition($source_entity, FALSE);
          if ($source_definition instanceof ContentEntityTypeInterface && $source_definition->getKey('id') === $source_field) {
            $target_entity_type = $source_entity;
          }
          else {
            $field_storage_config = $field_storage_configs[$source_entity . '.' . $source_field] ?? NULL;
            $target_entity_type = $field_storage_config?->getSetting('target_type');
          }
        }

        if (!$target_entity_type) {
          continue;
        }

        /** @var \Drupal\Core\Entity\EntityTypeInterface $target_entity_type_info */
        $target_entity_type_info = $this->entityTypeManager->getDefinition($target_entity_type, FALSE);
        if (!$target_entity_type_info) {
          continue;
        }

        // Content entities only — config entity support is a future @todo.
        if ($target_entity_type_info instanceof ContentEntityTypeInterface) {
          $base_table = $target_entity_type_info->getDataTable();
          if (!$base_table) {
            $base_table = $target_entity_type_info->getBaseTable();
          }
        }

        if (empty($base_table)) {
          continue;
        }

        if (!empty($field_info['filter']['id']) && $field_info['filter']['id'] !== 'entityreference_filter_view_result') {
          $filter = $field_info;

          $title = !empty($field_info['filter']['title']) ?
              $field_info['filter']['title'] : $field_info['title'] ?? '';

          $filter['title'] = $this->t('@title (entityreference filter)', ['@title' => $title]);

          $title_short = !empty($field_info['filter']['title short']) ?
            $field_info['filter']['title short'] : $field_info['title'] ?? '';

          $filter['title short'] = $this->t('@title (ef)', ['@title' => $title_short]);

          if (mb_strpos($table_name, 'search_api_index') !== FALSE && mb_strpos($field_info['filter']['id'], 'search_api_') === 0) {
            // The Search API variant lives in the optional integration
            // submodule. Skip the filter entirely when that plugin is missing.
            if (!$this->viewsFilterManager->hasDefinition('entityreference_filter_view_result_search_api')) {
              continue;
            }
            $filter['filter']['id'] = 'entityreference_filter_view_result_search_api';
          }
          else {
            $filter['filter']['id'] = 'entityreference_filter_view_result';
          }

          $filter['filter']['field'] = $field_name;
          $filter['filter']['filter_base_table'] = $base_table;

          // Adds only filter field.
          unset($filter['filter']['title'], $filter['argument'], $filter['field'], $filter['relationship'], $filter['sort']);

          $data[$table_name][$field_name . '_entityreference_filter'] = $filter;
        }
      }
    }
  }

}
