<?php

declare(strict_types=1);

namespace Drupal\Tests\node_revision_delete\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Queue admin page.
 *
 * @covers \Drupal\node_revision_delete\Form\QueueForm
 */
#[Group('node_revision_delete')]
#[RunTestsInSeparateProcesses]
class QueueFormTest extends BrowserTestBase {
  use CronRunTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'node_revision_delete',
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
   * Tests the queue form displays counts and queues nodes on submit.
   */
  public function testQueueForm(): void {
    for ($i = 1; $i <= 5; $i++) {
      $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Test ' . $i,
      ]);
    }

    $user = $this->drupalCreateUser(['administer node_revision_delete']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/content/node_revision_delete/queue');

    // Verify the page displays queue counts.
    $this->assertSession()->pageTextContains('Queue status');
    $this->assertSession()->pageTextContains('Revision delete');
    $this->assertSession()->pageTextContains('Time-based requeue');

    // Verify the submit button is present.
    $this->assertSession()->buttonExists('Queue all content for revision deletion');

    // Both queues should be empty initially.
    $queue = \Drupal::service('queue')->get('node_revision_delete');
    $this->assertSame(0, $queue->numberOfItems());

    // Submitting without a plugin enabled should not queue anything.
    $this->submitForm([], 'Queue all content for revision deletion');
    $this->assertSame(0, $queue->numberOfItems());

    // Enable a plugin and submit.
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
    $this->drupalGet('admin/config/content/node_revision_delete/queue');
    $this->submitForm([], 'Queue all content for revision deletion');
    $this->assertSame(5, $queue->numberOfItems());

    // Verify the queue count is reflected on the page after reload.
    $this->drupalGet('admin/config/content/node_revision_delete/queue');
    $this->assertQueueCount('Revision delete', 5);
    $this->assertQueueCount('Time-based requeue', 0);

    // Add a requeue item and verify the requeue count is displayed. This is a
    // hacky way to add queue items but we are only testing the count here.
    $requeue = \Drupal::service('queue')->get('node_revision_delete_requeue');
    $requeue->createItem(['nid' => 1, 'requeue_time' => \Drupal::time()->getCurrentTime() + 3600]);
    $requeue->createItem(['nid' => 2, 'requeue_time' => \Drupal::time()->getCurrentTime() + 3600]);
    $this->drupalGet('admin/config/content/node_revision_delete/queue');
    $this->assertQueueCount('Time-based requeue', 2);

    // Run cron.
    $this->cronRun();
    $this->drupalGet('admin/config/content/node_revision_delete/queue');
    $this->assertQueueCount('Revision delete', 0);
    $this->assertQueueCount('Time-based requeue', 2);

    // Re-submit the form removes the requeue items as all nodes are queued..
    $this->drupalGet('admin/config/content/node_revision_delete/queue');
    $this->submitForm([], 'Queue all content for revision deletion');
    $this->assertQueueCount('Revision delete', 5);
    $this->assertQueueCount('Time-based requeue', 0);
  }

  /**
   * Asserts the item count displayed for a queue in the status table.
   *
   * @param string $queue_label
   *   The queue label as shown in the table.
   * @param int $expected_count
   *   The expected item count.
   */
  private function assertQueueCount(string $queue_label, int $expected_count): void {
    $row = $this->assertSession()->elementExists('xpath', '//table//tr[td[text()="' . $queue_label . '"]]');
    $cells = $row->findAll('css', 'td');
    $this->assertSame((string) $expected_count, $cells[1]->getText());
  }

}
