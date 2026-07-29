<?php

declare(strict_types=1);

namespace Drupal\Tests\node_revision_delete\Functional;

use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the node-revision-delete:queue Drush command.
 *
 * @covers \Drupal\node_revision_delete\Commands\PriorRevisions
 */
#[Group('node_revision_delete')]
#[RunTestsInSeparateProcesses]
class DrushQueueTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'revision' => TRUE]);
  }

  /**
   * Tests creating the node_revision_delete queue with  the Drush command.
   */
  public function testDeletePriorRevisions(): void {
    // Create more nodes than
    // \Drupal\node_revision_delete\NodeRevisionDeleteBatch::NODES_PER_BATCH_PROCESS.
    for ($i = 1; $i <= 501; $i++) {
      $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Test ' . $i,
      ]);
    }
    \Drupal::service('module_installer')->install(['node_revision_delete']);
    $this->rebuildAll();

    $queue = \Drupal::service('queue')->get('node_revision_delete');
    $this->assertSame(0, $queue->numberOfItems());
    $this->drush('node-revision-delete:queue');
    $this->assertSame(0, $queue->numberOfItems());
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
    $this->drush('node-revision-delete:queue');
    $this->assertSame(501, $queue->numberOfItems());
  }

}
