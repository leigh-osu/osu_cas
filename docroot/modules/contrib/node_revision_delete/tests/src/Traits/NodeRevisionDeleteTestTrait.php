<?php

declare(strict_types=1);

namespace Drupal\Tests\node_revision_delete\Traits;

use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Test base for the node revision delete plugins.
 */
trait NodeRevisionDeleteTestTrait {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * Runs the node revision delete queue for the test.
   *
   * @param int $number_of_items
   *   (optional) The expected number of queue items. Defaults to 1.
   */
  private function runNodeRevisionDeleteQueue(int $number_of_items = 1): void {
    $queue = $this->container->get('queue')->get('node_revision_delete');

    // Assert a queue item has been created.
    $this->assertEquals($number_of_items, $queue->numberOfItems());

    $this->container->get('cron')->run();

    // Assert the queue item has been processed.
    $this->assertEquals(0, $queue->numberOfItems());
  }

  /**
   * Runs the node revision delete requeue for the test.
   *
   * @param int $number_of_items
   *   (optional) The expected number of queue items. Defaults to 1.
   * @param int $number_of_requeued_items
   *   (optional) The expected number of items that will be requeued. Defaults
   *   to 0.
   */
  private function runNodeRevisionDeleteRequeue(int $number_of_items = 1, int $number_of_requeued_items = 0): void {
    $queue = $this->container->get('queue')->get('node_revision_delete_requeue');

    // Assert a queue item has been created.
    $this->assertEquals($number_of_items, $queue->numberOfItems());

    $this->container->get('cron')->run();

    // Assert the queue item left.
    $this->assertEquals($number_of_requeued_items, $queue->numberOfItems());
  }

  /**
   * Asserts that the number of revisions for a node is as expected.
   *
   * @param int $expected
   *   The expected number of revisions.
   * @param \Drupal\node\NodeInterface $node
   *   The node to count revisions for.
   */
  private function assertRevisionCount(int $expected, NodeInterface $node): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $query = $storage->getQuery()->allRevisions()->condition('nid', $node->id())->accessCheck(FALSE);
    $revision_ids = array_keys($query->execute());
    $this->assertCount($expected, $revision_ids, sprintf('Expected %d revisions, but found %d.', $expected, count($revision_ids)));
  }

}
