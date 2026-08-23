<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_field\Kernel;

use Drupal\Core\Database\Database;
use Drupal\node\NodeInterface;
use Drupal\Tests\ui_patterns\Kernel\SourceTree\TranslationBase;
use Drupal\ui_patterns_field\Field\SourceValueList;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Legacy rows stored without node_ids must not corrupt the default language.
 *
 * Two structurally identical trees must share a structure signature even
 * without node_ids; otherwise a leaf edit on a translation is mistaken for
 * a structural change and clobbers the default language's leaves.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns_field')]
#[RunTestsInSeparateProcesses]
final class LegacyDataWithoutNodeIdsTest extends TranslationBase {

  /**
   * A leaf edit on a legacy translation must not clobber default leaves.
   */
  public function testLegacyTranslationLeafEditKeepsDefaultLeaves(): void {
    $leaf_path = ['source', 'component', 'slots', 'content', 'sources', 0, 'source', 'value', 'value'];

    $this->node->set('field_source', [$this->testSourceTreeData[0]]);
    $this->node->save();
    $german_node = $this->node->addTranslation('de', $this->node->toArray());
    $german_node->setTitle('deutsch');
    $values = $german_node->get('field_source')->getValue();
    $this->setLeaf($values[0], $leaf_path, 'deutsch');
    $german_node->set('field_source', $values);
    $german_node->save();

    $this->stripStoredNodeIds();

    // Edit only a leaf on the DE translation of the legacy data.
    $reloaded = $this->reloadNode();
    $german_reloaded = $reloaded->getTranslation('de');
    $de_list = $german_reloaded->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $de_list);
    $de_values = $de_list->getRawValues();
    $this->setLeaf($de_values[0], $leaf_path, 'geaendert');
    $german_reloaded->set('field_source', $de_values);
    $german_reloaded->save();

    $reloaded = $this->reloadNode();
    $en_values = $reloaded->getUntranslated()->get('field_source')->getValue();
    self::assertSame(
      'english',
      $this->getLeaf($en_values[0], $leaf_path),
      'Default language leaves survive a leaf-only edit on a legacy translation.',
    );
    self::assertSame(
      'Default Value',
      $en_values[0]['source']['component']['props']['attributes']['source']['value'],
      'Default language prop leaves survive as well.',
    );

    $de_list = $reloaded->getTranslation('de')->get('field_source');
    self::assertInstanceOf(SourceValueList::class, $de_list);
    self::assertSame(
      'geaendert',
      $this->getLeaf($de_list->getRawValues()[0], $leaf_path),
      'The translation keeps its own edited leaf.',
    );
  }

  /**
   * Removes all node_ids from the stored field rows, simulating legacy data.
   */
  private function stripStoredNodeIds(): void {
    $connection = Database::getConnection();
    foreach (['node__field_source', 'node_revision__field_source'] as $table) {
      $rows = $connection->select($table, 't')
        ->fields('t', ['entity_id', 'revision_id', 'langcode', 'delta', 'field_source_source'])
        ->execute()
        ->fetchAll();
      foreach ($rows as $row) {
        $source = unserialize($row->field_source_source, ['allowed_classes' => FALSE]);
        $connection->update($table)
          ->fields([
            'field_source_node_id' => '',
            'field_source_source' => serialize(is_array($source) ? $this->stripNodeIds($source) : $source),
          ])
          ->condition('entity_id', $row->entity_id)
          ->condition('revision_id', $row->revision_id)
          ->condition('langcode', $row->langcode)
          ->condition('delta', $row->delta)
          ->execute();
      }
    }
    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
  }

  /**
   * Recursively removes node_id keys from a tree.
   *
   * @param array $tree
   *   The tree to clean.
   *
   * @return array
   *   The tree without node_ids.
   */
  private function stripNodeIds(array $tree): array {
    unset($tree['node_id']);
    foreach ($tree as $key => $value) {
      if (is_array($value)) {
        $tree[$key] = $this->stripNodeIds($value);
      }
    }
    return $tree;
  }

  /**
   * Sets a nested leaf value.
   *
   * @param array $item
   *   The field item values, modified by reference.
   * @param array $path
   *   The parent keys of the leaf.
   * @param string $value
   *   The new leaf value.
   */
  private function setLeaf(array &$item, array $path, string $value): void {
    $ref = &$item;
    foreach ($path as $key) {
      $ref = &$ref[$key];
    }
    $ref = $value;
  }

  /**
   * Gets a nested leaf value.
   *
   * @param array $item
   *   The field item values.
   * @param array $path
   *   The parent keys of the leaf.
   *
   * @return mixed
   *   The leaf value.
   */
  private function getLeaf(array $item, array $path): mixed {
    $ref = $item;
    foreach ($path as $key) {
      $ref = $ref[$key] ?? NULL;
      if ($ref === NULL) {
        return NULL;
      }
    }
    return $ref;
  }

  /**
   * Reloads the test node bypassing the static cache.
   *
   * @return \Drupal\node\NodeInterface
   *   The reloaded node.
   */
  private function reloadNode(): NodeInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    $reloaded = $storage->load($this->node->id());
    self::assertInstanceOf(NodeInterface::class, $reloaded);
    return $reloaded;
  }

}
