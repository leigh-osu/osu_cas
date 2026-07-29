<?php

namespace Drupal\paragraphs_to_layout_builder\Plugin\migrate\source\d7;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\migrate\Row;
use Drupal\paragraphs\Plugin\migrate\source\d7\ParagraphsItem;

/**
 * Paragraphs Item source that recovers live-but-mislabelled paragraphs.
 *
 * Core's d7_paragraphs_item drives off paragraphs_item.revision_id and
 * field_name, and then looks the parent up in field_data_<field_name> using
 * that revision id. Live D7 sites accumulate two kinds of stale pointers in
 * paragraphs_item that break this:
 * - revision_id names a different revision than the parent's current field
 *   data references;
 * - field_name names a field that never (or no longer) hosts the item, so the
 *   parent lookup searches the wrong table entirely.
 * Either way the parent lookup returns nothing, prepareRow() bails, the block
 * is never created, and the node migration later logs "Unable to find related
 * migrated block".
 *
 * The authoritative answer for "which field and revision are live" is the
 * host entity's current field data (field_data_<field>), which by D7 design
 * only ever holds the live deltas. Before delegating to the core
 * implementation we look the item up in the declared field's data table and,
 * failing that, in every other paragraphs field's data table, then repoint the
 * row's field_name and revision_id at what the live data actually says, so
 * the core field-value load and parent lookup both succeed.
 *
 * @MigrateSource(
 *   id = "osu_d7_paragraphs_item",
 *   source_module = "paragraphs",
 * )
 */
class OsuParagraphsItem extends ParagraphsItem {

  /**
   * Machine names of all D7 paragraphs reference fields, lazily loaded.
   *
   * @var string[]|null
   */
  protected $paragraphsFieldNames = NULL;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    [
      'item_id' => $paragraph_id,
      'revision_id' => $paragraph_revision_id,
      'field_name' => $paragraph_parent_field_name,
    ] = $row->getSource();

    if ($paragraph_parent_field_name && is_string($paragraph_parent_field_name)) {
      $live_host = $this->findLiveHost($paragraph_parent_field_name, $paragraph_id);
      if ($live_host !== NULL) {
        // Only override when the live data genuinely disagrees with the stale
        // paragraphs_item pointers; otherwise leave core's behaviour
        // untouched so unaffected paragraphs migrate exactly as before.
        if ($live_host['field_name'] !== $paragraph_parent_field_name) {
          $row->setSourceProperty('field_name', $live_host['field_name']);
        }
        if ((string) $live_host['revision_id'] !== (string) $paragraph_revision_id) {
          $row->setSourceProperty('revision_id', $live_host['revision_id']);
        }
      }
    }

    return parent::prepareRow($row);
  }

  /**
   * Find the field and revision the live field data hosts the item under.
   *
   * The declared field is checked first so healthy rows short-circuit; only
   * when the item is absent from its declared field's data table are the
   * other paragraphs fields probed.
   *
   * @param string $declared_field_name
   *   The field machine name recorded in paragraphs_item.field_name.
   * @param int|string $paragraph_id
   *   The paragraph item_id.
   *
   * @return array|null
   *   An array with 'field_name' and 'revision_id' keys describing the live
   *   host row, or NULL when no live field data references the item.
   */
  protected function findLiveHost($declared_field_name, $paragraph_id) {
    $revision_id = $this->findLiveRevisionId($declared_field_name, $paragraph_id);
    if ($revision_id !== NULL) {
      return [
        'field_name' => $declared_field_name,
        'revision_id' => $revision_id,
      ];
    }

    foreach ($this->getParagraphsFieldNames() as $field_name) {
      if ($field_name === $declared_field_name) {
        continue;
      }
      $revision_id = $this->findLiveRevisionId($field_name, $paragraph_id);
      if ($revision_id !== NULL) {
        return [
          'field_name' => $field_name,
          'revision_id' => $revision_id,
        ];
      }
    }

    return NULL;
  }

  /**
   * List every paragraphs reference field defined in the source site.
   *
   * @return string[]
   *   Machine names of active, non-deleted D7 paragraphs fields.
   */
  protected function getParagraphsFieldNames() {
    if ($this->paragraphsFieldNames === NULL) {
      try {
        $this->paragraphsFieldNames = $this->getDatabase()
          ->select('field_config', 'fc')
          ->fields('fc', ['field_name'])
          ->condition('fc.type', 'paragraphs')
          ->condition('fc.active', 1)
          ->condition('fc.deleted', 0)
          ->execute()
          ->fetchCol();
      }
      catch (DatabaseExceptionWrapper $e) {
        $this->paragraphsFieldNames = [];
      }
    }

    return $this->paragraphsFieldNames;
  }

  /**
   * Find the paragraph revision the parent's current field data references.
   *
   * @param string $field_name
   *   The parent field machine name (paragraphs_item.field_name).
   * @param int|string $paragraph_id
   *   The paragraph item_id.
   *
   * @return int|string|null
   *   The revision id referenced by the live field data, or NULL when it
   *   cannot be determined (no live row, or the field data table is missing).
   */
  protected function findLiveRevisionId($field_name, $paragraph_id) {
    try {
      $query = $this->getDatabase()
        ->select(static::PARENT_FIELD_TABLE_PREFIX . $field_name, 'fd');
      $query->addField('fd', $field_name . '_revision_id', 'revision_id');
      $revision_id = $query
        ->condition("fd.{$field_name}_value", $paragraph_id)
        ->range(0, 1)
        ->execute()
        ->fetchField();
    }
    catch (DatabaseExceptionWrapper $e) {
      // The field data table is missing (corrupted/partial D7 database); let
      // the core implementation handle it the same way it always has.
      return NULL;
    }

    return ($revision_id === FALSE || $revision_id === NULL) ? NULL : $revision_id;
  }

}
