<?php

declare(strict_types=1);

namespace Drupal\Tests\node_revision_delete\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node_revision_delete\NodeRevisionDeleteInterface;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the node revision delete plugins.
 */
#[Group('node_revision_delete')]
#[RunTestsInSeparateProcesses]
class NodeRevisionUpdateTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use ContentModerationTestTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'node',
    'node_revision_delete',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('node_revision_delete', [NodeRevisionDeleteInterface::QUEUE_SEMAPHORE_TABLE]);
    $this->installConfig([
      'system',
      'filter',
      'node',
    ]);

    // Create a node type that allows revisions.
    $this->createContentType(['type' => 'page', 'revision' => TRUE]);
  }

  /**
   * Tests node_revision_delete_update_21001().
   */
  public function testUpdate(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Configure the default settings for the "amount" plugin to allow a maximum
    // of 5 revisions.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'amount' => [
          'status' => TRUE,
          'settings' => [
            'amount' => 5,
          ],
        ],
      ])
      ->save();

    // Create 200 nodes each with 1 revision to trigger queueing.
    for ($i = 0; $i < 200; $i++) {
      $node = $this->createNode(['type' => 'page']);
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }

    $queue = $this->container->get('queue')->get('node_revision_delete');
    $this->assertSame(200, $queue->numberOfItems());

    // Clear the queue map table to test that the update populates it correctly.
    $this->container->get('database')->schema()->dropTable(NodeRevisionDeleteInterface::QUEUE_SEMAPHORE_TABLE);

    $this->container->get('module_handler')->loadInclude('node_revision_delete', 'install');
    // Run the update to create the queue map table.
    node_revision_delete_update_21000();

    $context = [];
    do {
      // Run the update to populate the queue map table.
      node_revision_delete_update_21001($context);
    } while ($context['#finished'] < 1);

    $count = $this->container->get('database')->select(NodeRevisionDeleteInterface::QUEUE_SEMAPHORE_TABLE, 'nrd')->fields('nrd', ['nid'])->countQuery()->execute()->fetchField();
    $this->assertSame(200, (int) $count);
    $this->assertSame(200, $queue->numberOfItems());
    // Ensure the map is working as expected.
    $this->assertTrue($this->container->get('node_revision_delete')->nodeExistsInQueue((int) $node->id()));
  }

}
