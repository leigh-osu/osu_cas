<?php

declare(strict_types=1);

namespace Drupal\Tests\entityreference_filter\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that id columns exposed via a Views relationship get the filter.
 *
 * The hook adds the entityreference filter not only to an entity's own id on
 * its base/data table, but also to id columns Views exposes on other tables
 * through a relationship (revision tables, foreign keys such as the author
 * uid). The relationship's base table + base field identify the target entity.
 *
 * @group entityreference_filter
 *
 * @covers \Drupal\entityreference_filter\Hook\EntityReferenceFilterHooks::viewsDataAlter
 */
#[RunTestsInSeparateProcesses]
class ViewsDataRelationshipIdTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'taxonomy',
    'views',
    'entityreference_filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
  }

  /**
   * Returns all Views data.
   *
   * @return array
   *   The full Views data array.
   */
  protected function viewsData(): array {
    return \Drupal::service('views.views_data')->getAll();
  }

  /**
   * A foreign id column (node author uid) gets a filter targeting that entity.
   */
  public function testForeignIdColumnGetsFilter(): void {
    $data = $this->viewsData();
    $filter = $data['node_field_data']['uid_entityreference_filter'] ?? NULL;

    $this->assertIsArray($filter, 'The node author uid receives an entityreference filter.');
    $this->assertSame('entityreference_filter_view_result', $filter['filter']['id']);
    $this->assertSame('users_field_data', $filter['filter']['filter_base_table']);
    $this->assertSame('uid', $filter['filter']['field']);
  }

  /**
   * An entity's own id on a revision table gets a filter targeting the entity.
   */
  public function testOwnIdOnRevisionTableGetsFilter(): void {
    $data = $this->viewsData();
    $filter = $data['node_field_revision']['nid_entityreference_filter'] ?? NULL;

    $this->assertIsArray($filter, 'The revision table nid receives an entityreference filter.');
    $this->assertSame('entityreference_filter_view_result', $filter['filter']['id']);
    $this->assertSame('node_field_data', $filter['filter']['filter_base_table']);
  }

  /**
   * Columns without an id relationship are left untouched.
   */
  public function testNonIdColumnIsNotAltered(): void {
    $data = $this->viewsData();

    // 'created' is a timestamp with no relationship to an entity id.
    $this->assertArrayNotHasKey('created_entityreference_filter', $data['node_field_data']);
    // The added field never duplicates itself (its relationship is stripped).
    $this->assertArrayNotHasKey('uid_entityreference_filter_entityreference_filter', $data['node_field_data']);
  }

}
