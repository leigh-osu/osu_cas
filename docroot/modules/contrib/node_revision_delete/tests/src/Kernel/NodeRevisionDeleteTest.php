<?php

declare(strict_types=1);

namespace Drupal\Tests\node_revision_delete\Kernel;

use Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Queue\DatabaseQueue;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drupal\node_revision_delete\NodeRevisionDeleteInterface;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\node_revision_delete\Traits\NodeRevisionDeleteTestTrait;
use Drupal\Tests\node_revision_delete\Traits\TimePatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the node revision delete plugins.
 */
#[Group('node_revision_delete')]
#[RunTestsInSeparateProcesses]
class NodeRevisionDeleteTest extends KernelTestBase {
  use ContentModerationTestTrait;
  use NodeRevisionDeleteTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'content_moderation',
    'field',
    'filter',
    'language',
    'node',
    'node_revision_delete',
    'system',
    'text',
    'user',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('node_revision_delete', [NodeRevisionDeleteInterface::QUEUE_SEMAPHORE_TABLE]);
    $this->installConfig([
      'system',
      'filter',
      'node',
      'node_revision_delete',
      'content_moderation',
    ]);

    // Add the Dutch language.
    ConfigurableLanguage::createFromLangcode('nl')->save();

    // Create a node type that allows revisions.
    $this->createContentType(['type' => 'page', 'revision' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    // Set up time patching so we can test requeuing.
    $container->getDefinition('datetime.time')->setClass(TimePatcher::class);
  }

  /**
   * Test the node revision delete "amount" plugin.
   */
  public function testNodeRevisionDeleteAmount(): void {
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

    // Create 10 revisions.
    $node = $this->createNode(['type' => 'page']);
    for ($i = 0; $i < 10; $i++) {
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }
    $this->runNodeRevisionDeleteQueue();

    // Assert that only 5 revisions remain.
    $this->assertRevisionCount(5, $node);

    // Override the default settings for page node type to allow a maximum of 3
    // revisions.
    $node_type = $this->container->get('entity_type.manager')->getStorage('node_type')->load('page');
    $node_type->setThirdPartySetting('node_revision_delete', 'amount', [
      'status' => TRUE,
      'settings' => [
        'amount' => 3,
      ],
    ]);
    $node_type->save();

    // Add a revision and run the queue.
    $new_revision = $node_storage->createRevision($node);
    $new_revision->save();
    $this->runNodeRevisionDeleteQueue();

    // Assert that only 3 revisions remain.
    $this->assertRevisionCount(3, $node);
  }

  /**
   * Test the queue processing a deleted node.
   */
  public function testDeletedNode(): void {
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

    // Create 10 revisions.
    $node = $this->createNode(['type' => 'page']);
    for ($i = 0; $i < 10; $i++) {
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }

    $nid = (int) $node->id();
    $queue = $this->container->get('queue')->get('node_revision_delete');
    $this->assertInstanceOf(DatabaseQueue::class, $queue);

    // Test processing the queue with an existing node.
    $this->assertSame(1, $queue->numberOfItems());
    $this->assertTrue($this->container->get('node_revision_delete')->nodeExistsInQueue($nid));
    $this->runNodeRevisionDeleteQueue();
    $this->assertFalse($this->container->get('node_revision_delete')->nodeExistsInQueue($nid));
    $this->assertSame(0, $queue->numberOfItems());

    // Create another revision.
    $new_revision = $node_storage->createRevision($node);
    $new_revision->save();
    $this->assertTrue($this->container->get('node_revision_delete')->nodeExistsInQueue($nid));
    $this->assertSame(1, $queue->numberOfItems());

    // Delete the node.
    $new_revision->delete();

    // This does not remove it from the queue but processing does and it cleans
    // up without error.
    $this->assertSame(1, $queue->numberOfItems());
    $this->assertTrue($this->container->get('node_revision_delete')->nodeExistsInQueue($nid));
    $this->runNodeRevisionDeleteQueue();
    $this->assertFalse($this->container->get('node_revision_delete')->nodeExistsInQueue($nid));
    $this->assertSame(0, $queue->numberOfItems());
  }

  /**
   * Test the node revision delete "created" plugin.
   */
  public function testNodeRevisionDeleteCreated() {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Configure the default settings for the "created" plugin to allow
    // revisions to exist for a maximum of 5 months.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 5,
          ],
        ],
      ])
      ->save();

    // Create 10 revisions, each 1 month newer than the previous.
    $node = $this->createNode([
      'type' => 'page',
      'created' => strtotime('-10 months -1 day'),
      'changed' => strtotime('-10 months -1 day'),
    ]);
    for ($i = 9; $i >= 0; $i--) {
      $node->setChangedTime(strtotime('-' . $i . ' months -1 day'));
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
      $this->runNodeRevisionDeleteQueue();
    }

    // Assert that only 5 revisions remain. The items created 5 months ago
    // (and before) should have been deleted.
    $this->assertRevisionCount(5, $node);

    // Override the default settings for page node type to allow revisions to
    // exist for a maximum of 3 months.
    $node_type = $this->container->get('entity_type.manager')->getStorage('node_type')->load('page');
    $node_type->setThirdPartySetting('node_revision_delete', 'created', [
      'status' => TRUE,
      'settings' => [
        'age' => 3,
      ],
    ]);
    $node_type->save();

    // Add a revision and run the queue.
    $new_revision = $node_storage->createRevision($node);
    $new_revision->save();
    $this->runNodeRevisionDeleteQueue();

    // Assert that 4 revisions remain. The latest revision should be kept, and
    // 3 of the revisions that were created previously.
    $this->assertRevisionCount(4, $node);
  }

  /**
   * Test the node revision delete "drafts" plugin.
   */
  public function testNodeRevisionDeleteDrafts(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Add the editorial workflow for the node type.
    $workflow = $this->createEditorialWorkflow();
    $workflow_type = $workflow->getTypePlugin();
    $this->assertInstanceOf(ContentModerationInterface::class, $workflow_type);
    $workflow_type->addEntityTypeAndBundle('node', 'page');
    $workflow->save();

    // Configure the default settings for the "drafts" plugin to allow draft
    // revisions to exist for a maximum of 5 months.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'drafts' => [
          'status' => TRUE,
          'settings' => [
            'age' => 5,
          ],
        ],
      ])
      ->save();

    // Create 10 draft revisions, each a month newer than the previous.
    $node = $this->createNode([
      'type' => 'page',
      'created' => strtotime('-10 months -1 day'),
      'changed' => strtotime('-10 months -1 day'),
      'status' => NodeInterface::PUBLISHED,
      'moderation_state' => 'published',
    ]);
    for ($i = 9; $i >= 0; $i--) {
      $node->set('moderation_state', 'draft');
      $node->setChangedTime(strtotime('-' . $i . ' months -1 day'));
      $new_revision = $node_storage->createRevision($node, FALSE);
      $new_revision->save();
      $this->runNodeRevisionDeleteQueue();
    }

    // Assert that 6 revisions remain. The draft items created 5 months ago
    // (and before) should have been deleted. The published revision should also
    // be kept.
    $this->assertRevisionCount(6, $node);

    // Override the default settings for page node type to allow draft revisions
    // to exist for a maximum of 3 months.
    $node_type = $this->container->get('entity_type.manager')->getStorage('node_type')->load('page');
    $node_type->setThirdPartySetting('node_revision_delete', 'drafts', [
      'status' => TRUE,
      'settings' => [
        'age' => 3,
      ],
    ]);
    $node_type->save();

    // Add a draft revision and run the queue.
    $node->set('moderation_state', 'draft');
    $new_revision = $node_storage->createRevision($node, FALSE);
    $new_revision->save();
    $this->runNodeRevisionDeleteQueue();

    // Assert that 5 revisions remain. The latest draft revision should be kept,
    // and 3 of the revisions that were created previously. The published
    // revision should also be kept.
    $this->assertRevisionCount(5, $node);

    // The Drafts plugin is time-based, so the protected drafts (younger than
    // 3 months) should cause a requeue item.
    $requeue = $this->container->get('queue')->get('node_revision_delete_requeue');
    $this->assertGreaterThan(0, $requeue->numberOfItems());

    // Advance time past the 3-month age threshold so all remaining drafts
    // become deletable.
    TimePatcher::setPatch(60 * 60 * 24 * 31 * 4);
    $this->container->get('cron')->run();
    $this->container->get('cron')->run();
    TimePatcher::setPatch(0);

    // Only the published revision should remain.
    $this->assertRevisionCount(1, $node);
  }

  /**
   * Test the node revision delete "only drafts" plugin.
   */
  public function testNodeRevisionDeleteOnlyDrafts(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    // Add the editorial workflow for the node type.
    $workflow = $this->createEditorialWorkflow();
    $workflow_type = $workflow->getTypePlugin();
    $this->assertInstanceOf(ContentModerationInterface::class, $workflow_type);
    $workflow_type->addEntityTypeAndBundle('node', 'page');
    $workflow->save();

    // Configure the default settings for the "drafts" plugin to allow draft
    // revisions to exist for a maximum of 5 months.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'only_drafts' => [
          'status' => TRUE,
          'settings' => [
            'age' => 5,
          ],
        ],
      ])
      ->save();

    // Create 10 draft revisions, each 31 days newer than the previous.
    $node = $this->createNode([
      'type' => 'page',
      'created' => strtotime('-' . (10 * 31) . ' days'),
      'changed' => strtotime('-' . (10 * 31) . ' days'),
      'status' => NodeInterface::PUBLISHED,
      'moderation_state' => 'published',
    ]);
    for ($i = 9; $i >= 0; $i--) {
      $node = $node_storage->createRevision($node);
      $node->set('moderation_state', 'draft');
      $node->setChangedTime(strtotime('-' . ($i * 31) . ' days'));
      $node->save();
      $this->runNodeRevisionDeleteQueue();
    }
    // Assert that 11 revisions remain. No drafts are older than a published
    // revision.
    $this->assertRevisionCount(11, $node);

    $node = $node_storage->createRevision($node);
    $node->setChangedTime(time());
    $node->set('moderation_state', 'published');
    $node->save();
    $this->runNodeRevisionDeleteQueue();
    // Assert that 7 revisions remain. 2 published revisions and the 5 latest
    // drafts are kept.
    $this->assertRevisionCount(7, $node);
  }

  /**
   * Test the node revision delete plugin integration.
   */
  public function testNodeRevisionDeleteIntegration(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Add the editorial workflow for the node type.
    $workflow = $this->createEditorialWorkflow();
    $workflow_type = $workflow->getTypePlugin();
    $this->assertInstanceOf(ContentModerationInterface::class, $workflow_type);
    $workflow_type->addEntityTypeAndBundle('node', 'page');
    $workflow->save();

    // Configure the default settings for the plugins to allow a maximum of 2
    // older revisions. Only revisions created 5 months ago (and before) should
    // be kept, and drafts should be kept for 3 months.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'amount' => [
          'status' => TRUE,
          'settings' => [
            'amount' => 2,
          ],
        ],
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 5,
          ],
        ],
        'drafts' => [
          'status' => TRUE,
          'settings' => [
            'age' => 4,
          ],
        ],
      ])
      ->save();

    // Create 10 published revisions, each month newer than the previous.
    $node = $this->createNode([
      'type' => 'page',
      'created' => strtotime('-10 months - 1 day'),
      'changed' => strtotime('-10 months - 1 day'),
      'status' => NodeInterface::PUBLISHED,
      'moderation_state' => 'published',
    ]);
    for ($i = 9; $i >= 0; $i--) {
      $node->setChangedTime(strtotime('-' . $i . ' months - 1 day'));
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
      $this->runNodeRevisionDeleteQueue();
    }

    // Assert that 5 revisions remain. The items created 5 months ago (and
    // before) should have been deleted. Since the "created" plugin prevents
    // deleting some revisions, the "amount" plugin should not have any effect.
    $this->assertRevisionCount(5, $node);

    // Create 10 draft revisions for the same node, each 1 month newer than the
    // previous.
    for ($i = 9; $i >= 0; $i--) {
      $node->set('moderation_state', 'draft');
      $node->setChangedTime(strtotime('-' . ($i) . ' months - 1 day'));
      $new_revision = $node_storage->createRevision($node, FALSE);
      $new_revision->save();
      $this->runNodeRevisionDeleteQueue();
    }

    // Assert that 9 revisions remain. The draft items created 4 months ago
    // (and before) should have been deleted. The published revisions should
    // also have been kept.
    $this->assertRevisionCount(9, $node);

    // Configure the default settings for the plugins to allow a maximum of 4
    // older revisions. Only revisions created 1 month ago (and before) should
    // be kept, and drafts should be kept for 3 months.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'amount' => [
          'status' => TRUE,
          'settings' => [
            'amount' => 4,
          ],
        ],
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 1,
          ],
        ],
        'drafts' => [
          'status' => TRUE,
          'settings' => [
            'age' => 3,
          ],
        ],
      ])
      ->save();

    // Add a draft revision and run the queue.
    $node->set('moderation_state', 'draft');
    $new_revision = $node_storage->createRevision($node, FALSE);
    $new_revision->save();
    $this->runNodeRevisionDeleteQueue();

    // Assert that 8 revisions remain. Since most revisions are more than 1
    // month old, they should be deleted, but the "amount" plugin should prevent
    // deleting the latest 4 published revisions. We previously created 3 draft
    // revisions that are less than 3 months old. They should still be kept. We
    // also just created a new draft revision, which should also be kept.
    $this->assertRevisionCount(8, $node);

    // Override the default settings for page node type to allow a maximum of 3
    // older revisions. Only revisions created 1 month ago (and before) should
    // be kept, and drafts should be kept for 2 months.
    $node_type = $this->container->get('entity_type.manager')->getStorage('node_type')->load('page');
    $node_type->setThirdPartySetting('node_revision_delete', 'amount', [
      'status' => TRUE,
      'settings' => [
        'amount' => 3,
      ],
    ]);
    $node_type->setThirdPartySetting('node_revision_delete', 'created', [
      'status' => TRUE,
      'settings' => [
        'age' => 1,
      ],
    ]);
    $node_type->setThirdPartySetting('node_revision_delete', 'drafts', [
      'status' => TRUE,
      'settings' => [
        'age' => 2,
      ],
    ]);
    $node_type->save();

    // Add a draft revision and run the queue.
    $node->set('moderation_state', 'draft');
    $new_revision = $node_storage->createRevision($node, FALSE);
    $new_revision->save();
    $this->runNodeRevisionDeleteQueue();

    // Assert that 7 revisions remain. Since most revisions are more than 1
    // month old, they should be deleted, but the "amount" plugin should prevent
    // deleting the latest 3 published revisions. We previously created 2 draft
    // revisions that are less than 2 months old. They should still be kept. We
    // also created 2 new draft revisions separately, which should also be kept.
    $this->assertRevisionCount(7, $node);
  }

  /**
   * Test that Amount with amount=1 does not block other plugins.
   *
   * When the Amount plugin is configured with amount=1 (keep only the active
   * revision), its getRevisionsToProtect() must return an empty set so that
   * other plugins (like Created) can still delete revisions.
   */
  public function testAmountDoesNotOverProtectInMultiPluginSetup(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Enable both Amount (amount=1) and Created (age=1 month).
    // Amount=1 means "keep only the active revision" so it should protect
    // nothing beyond the active. Created should delete anything older than
    // 1 month.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'amount' => [
          'status' => TRUE,
          'settings' => [
            'amount' => 1,
          ],
        ],
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 1,
          ],
        ],
      ])
      ->save();

    // Create a node with 5 revisions that are all older than 1 month.
    $node = $this->createNode([
      'type' => 'page',
      'created' => strtotime('-6 months'),
      'changed' => strtotime('-6 months'),
    ]);
    for ($i = 5; $i >= 2; $i--) {
      $node->setChangedTime(strtotime('-' . $i . ' months'));
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }

    // The latest revision is recent (not eligible for Created deletion).
    $node->setChangedTime(time());
    $new_revision = $node_storage->createRevision($node);
    $new_revision->save();

    // We now have 6 revisions: 5 old ones + 1 current.
    $this->assertRevisionCount(6, $node);

    $this->runNodeRevisionDeleteQueue();

    // Amount=1 means keep only the active revision (protect nothing beyond
    // it). Created deletes everything older than 1 month. Only the active
    // revision should remain.
    $this->assertRevisionCount(1, $node);
  }

  /**
   * Test that Created::cron() requeues nodes with old revisions.
   */
  public function testCreatedRequeuing(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Configure the "created" plugin with a 3-month age threshold.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 1,
          ],
        ],
      ])
      ->save();

    // Create a node with an old revision (4 months ago) that should be queued.
    $old_time = strtotime('-1 months -1 day');
    $node_old = $this->createNode([
      'type' => 'page',
      'created' => $old_time,
      'changed' => $old_time,
    ]);
    $node_old->save();
    // Create a second (current) revision so this node has a deletable old
    // revision.
    $new_revision = $node_storage->createRevision($node_old);
    $new_revision->setChangedTime(strtotime('-3 weeks'));
    $new_revision->save();

    // Create a node with only recent revisions (1 month ago) that should not
    // be queued.
    $recent_time = strtotime('-3 weeks');
    $node_recent = $this->createNode([
      'type' => 'page',
      'created' => $recent_time,
      'changed' => $recent_time,
    ]);
    $node_recent->save();
    $new_revision = $node_storage->createRevision($node_recent);
    $new_revision->setChangedTime(strtotime('-2 weeks -1 day'));
    $new_revision->save();

    $queue = $this->container->get('queue')->get('node_revision_delete');
    $requeue = $this->container->get('queue')->get('node_revision_delete_requeue');
    $this->assertSame(0, $requeue->numberOfItems());
    // Run the queue as if it was run 2 weeks ago. This should cause a node to
    // be requeued.
    TimePatcher::setPatch(-60 * 60 * 24 * 14);
    $this->runNodeRevisionDeleteQueue(2);
    $this->assertSame(0, $queue->numberOfItems());
    $this->assertSame(2, $requeue->numberOfItems());

    // Run cron, which should trigger Created::cron().
    $this->runNodeRevisionDeleteRequeue(2, 2);
    $this->assertSame(0, $queue->numberOfItems());
    $this->assertSame(2, $requeue->numberOfItems());

    // No revisions have been deleted yet.
    $this->assertRevisionCount(2, $node_old);
    $this->assertRevisionCount(2, $node_recent);

    TimePatcher::setPatch(0);
    // Run cron twice to ensure the nodes move between the queues.
    $this->container->get('cron')->run();
    $this->container->get('cron')->run();

    // The old node should have lost its old revision.
    $this->assertRevisionCount(1, $node_old);
    // The recent node should still have both revisions.
    $this->assertRevisionCount(2, $node_recent);

    $queue = $this->container->get('queue')->get('node_revision_delete');
    $this->assertSame(0, $queue->numberOfItems());
    $requeue = $this->container->get('queue')->get('node_revision_delete_requeue');
    $this->assertSame(1, $requeue->numberOfItems());
  }

  /**
   * Test that Created and Amount requeues nodes with old revisions.
   *
   * This ensures that nodes are only requeued when a time-based plugin protects
   * the old revision.
   */
  public function testCreatedAndAmountRequeuing(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Configure the "created" plugin with a 3-month age threshold.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 1,
          ],
        ],
        'amount' => [
          'status' => TRUE,
          'settings' => [
            'amount' => 2,
          ],
        ],
      ])
      ->save();

    // Create a node with an old revision (1 months ago).
    $old_time = strtotime('-1 months -1 day');
    $node_old = $this->createNode([
      'type' => 'page',
      'created' => $old_time,
      'changed' => $old_time,
    ]);
    $node_old->save();
    // Create a second revision so this node.
    $new_revision = $node_storage->createRevision($node_old);
    $new_revision->setChangedTime(strtotime('-4 weeks'));
    $new_revision->save();

    $queue = $this->container->get('queue')->get('node_revision_delete');
    $requeue = $this->container->get('queue')->get('node_revision_delete_requeue');
    $this->assertSame(0, $requeue->numberOfItems());
    // Run the queue as if it was run 2 weeks ago. As both revisions are
    // protected by the amount plugin nothing is requeued.
    TimePatcher::setPatch(-60 * 60 * 24 * 14);
    $this->runNodeRevisionDeleteQueue();
    $this->assertSame(0, $queue->numberOfItems());
    $this->assertSame(0, $requeue->numberOfItems());

    // Create a third revision so this node has a deletable old
    // revision.
    $new_revision = $node_storage->createRevision($node_old);
    $new_revision->setChangedTime(strtotime('-3 weeks'));
    $new_revision->save();

    // Run the queue as if it was run 2 weeks ago. This should cause a node to
    // be requeued.
    TimePatcher::setPatch(-60 * 60 * 24 * 14);
    $this->runNodeRevisionDeleteQueue();
    $this->assertSame(0, $queue->numberOfItems());
    $this->assertSame(1, $requeue->numberOfItems());
  }

  /**
   * Tests that Created and OnlyDrafts calculate the correct requeue delay.
   *
   * When both time-based plugins protect revisions, the requeue delay should
   * be the minimum of the delays calculated by each plugin based on the
   * highest exclusively-time-protected VID each is responsible for.
   */
  public function testCreatedAndOnlyDraftsDelay(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Add the editorial workflow for the node type.
    $workflow = $this->createEditorialWorkflow();
    $workflow_type = $workflow->getTypePlugin();
    $this->assertInstanceOf(ContentModerationInterface::class, $workflow_type);
    $workflow_type->addEntityTypeAndBundle('node', 'page');
    $workflow->save();

    // Configure Created (1 month) and OnlyDrafts (2 months).
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 1,
          ],
        ],
        'only_drafts' => [
          'status' => TRUE,
          'settings' => [
            'age' => 2,
          ],
        ],
      ])
      ->save();

    // Create a published node with an old revision (6 weeks ago).
    $old_time = strtotime('-6 weeks');
    $node = $this->createNode([
      'type' => 'page',
      'created' => $old_time,
      'changed' => $old_time,
      'status' => NodeInterface::PUBLISHED,
      'moderation_state' => 'published',
    ]);

    // Create a draft revision 3 weeks ago. This is:
    // - Protected by Created (changed 3 weeks ago, age threshold 1 month).
    // - Protected by OnlyDrafts (changed 3 weeks ago, age threshold 2 months,
    //   and it's unpublished + older than active).
    $draft = $node_storage->createRevision($node, FALSE);
    $draft->set('moderation_state', 'draft');
    $draft->setChangedTime(strtotime('-3 weeks'));
    $draft->save();

    // Create a new published revision (now) so the old published and draft
    // revisions are both older than the active revision.
    $published = $node_storage->createRevision($node);
    $published->set('moderation_state', 'published');
    $published->save();

    $this->assertRevisionCount(3, $node);

    $requeue = $this->container->get('queue')->get('node_revision_delete_requeue');

    // The requeue queue should be empty.
    $this->assertSame(0, $requeue->numberOfItems());

    // Process the queue. The old published revision (6 weeks) should be deleted
    // by Created (older than 1 month). The draft revision (3 weeks) is
    // protected by both Created and OnlyDrafts.
    $this->runNodeRevisionDeleteQueue();

    // The 6-week-old published revision should have been deleted.
    $this->assertRevisionCount(2, $node);

    // The draft is still protected, so a requeue item should exist.
    $this->assertSame(1, $requeue->numberOfItems());

    // Verify the delay is based on the plugin calculations, not the config
    // requeue_time. The draft was changed 3 weeks ago:
    // - Created delay: 1 month from changed (~1 week remaining).
    // - OnlyDrafts delay: 2 months from changed (~5 weeks remaining).
    // The minimum should be used, which is the Created delay (~1 week).
    $item = $this->container->get('database')
      ->select('queue', 'q')
      ->fields('q', ['data'])
      ->condition('name', 'node_revision_delete_requeue')
      ->execute()
      ->fetchField();
    $data = unserialize($item);
    $requeue_time = $data['requeue_time'];

    $expected_delay = strtotime('+1 months', strtotime('-3 weeks')) - time();
    $actual_delay = $requeue_time - time();

    // Allow a small tolerance for test execution time.
    $this->assertEqualsWithDelta($expected_delay, $actual_delay, 5, 'Requeue delay should match the Created plugin delay (the shorter of the two).');

    // Sanity check: the delay should be roughly 1 week, not 5 weeks.
    $this->assertGreaterThan(0, $actual_delay);
    $this->assertLessThan(60 * 60 * 24 * 14, $actual_delay, 'Delay should be less than 2 weeks.');
  }

  /**
   * Tests two time-based plugins protecting different revisions.
   *
   * When Created protects a published revision and OnlyDrafts protects a
   * different draft revision, the delay should be the minimum of the two
   * plugin-specific delays.
   */
  public function testCreatedAndOnlyDraftsDifferentRevisions(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Add the editorial workflow for the node type.
    $workflow = $this->createEditorialWorkflow();
    $workflow_type = $workflow->getTypePlugin();
    $this->assertInstanceOf(ContentModerationInterface::class, $workflow_type);
    $workflow_type->addEntityTypeAndBundle('node', 'page');
    $workflow->save();

    // Configure Created (2 months) and OnlyDrafts (1 month).
    // OnlyDrafts has the shorter age, so its delay will be shorter.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 2,
          ],
        ],
        'only_drafts' => [
          'status' => TRUE,
          'settings' => [
            'age' => 1,
          ],
        ],
      ])
      ->save();

    // Create a published node 7 weeks ago.
    $old_published_time = strtotime('-7 weeks');
    $node = $this->createNode([
      'type' => 'page',
      'created' => $old_published_time,
      'changed' => $old_published_time,
      'status' => NodeInterface::PUBLISHED,
      'moderation_state' => 'published',
    ]);

    // Create a draft revision 2 weeks ago. OnlyDrafts protects this
    // (unpublished, older than active, changed 2 weeks ago, age 1 month).
    // Created also protects it (changed 2 weeks ago, age 2 months).
    $draft = $node_storage->createRevision($node, FALSE);
    $draft->set('moderation_state', 'draft');
    $draft->setChangedTime(strtotime('-2 weeks'));
    $draft->save();

    // Create a new published revision (now). This makes the 7-week-old
    // published revision and the 2-week-old draft both older than active.
    $published = $node_storage->createRevision($node);
    $published->set('moderation_state', 'published');
    $published->save();

    $this->assertRevisionCount(3, $node);

    $requeue = $this->container->get('queue')->get('node_revision_delete_requeue');
    $this->assertSame(0, $requeue->numberOfItems());

    // Process. The 7-week-old published revision is:
    // - Deletable by Created (older than 2 months? No, 7 weeks < 2 months).
    //   Actually protected by Created.
    // - OnlyDrafts has no opinion (it's published, not a draft).
    // The 2-week-old draft is:
    // - Protected by Created (changed 2 weeks ago, age 2 months).
    // - Protected by OnlyDrafts (unpublished, changed 2 weeks ago, age 1 month).
    // Neither non-time-based plugin protects them, so both are exclusively
    // time-protected.
    $this->runNodeRevisionDeleteQueue();

    // Nothing deleted (both revisions are protected by time-based plugins).
    $this->assertRevisionCount(3, $node);
    $this->assertSame(1, $requeue->numberOfItems());

    // The 7-week-old published revision is only protected by Created. Its
    // delay: +2 months from 7 weeks ago.
    // The 2-week-old draft is the highest VID protected by both. Its delays:
    // - Created: +2 months from 7 weeks ago (~1 weeks remaining).
    // - OnlyDrafts: +1 month from 2 weeks ago (~2 weeks remaining).
    // The minimum delay should be Created' ~1 weeks.
    $item = $this->container->get('database')
      ->select('queue', 'q')
      ->fields('q', ['data'])
      ->condition('name', 'node_revision_delete_requeue')
      ->execute()
      ->fetchField();
    $data = unserialize($item);
    $requeue_time = $data['requeue_time'];

    $expected_delay = strtotime('+2 months', strtotime('-7 weeks')) - time();
    $actual_delay = $requeue_time - time();

    $this->assertEqualsWithDelta($expected_delay, $actual_delay, 5, 'Requeue delay should match the Created plugin delay (the shorter of the two).');

    // The delay should be less than 13 days.
    $this->assertGreaterThan(0, $actual_delay);
    $this->assertLessThan(60 * 60 * 24 * 13, $actual_delay, 'Delay should be less than 13 days.');
  }

  /**
   * Tests bulk deletion and time-based requeue in the same processing cycle.
   *
   * When a node has more deletable revisions than the bulk threshold AND some
   * revisions are exclusively time-protected, both the requeue item creation
   * and the bulk RequeueException should occur. Cron loops and processes all
   * bulk batches in a single run, but the time-protected revision survives.
   */
  public function testBulkDeleteWithTimeBasedRequeue(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Set a low bulk threshold so we can trigger it easily.
    $this->config('node_revision_delete.settings')
      ->set('bulk_delete_threshold', 2)
      ->set('defaults', [
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 1,
          ],
        ],
      ])
      ->save();

    // Create a node with many old revisions.
    $old_time = strtotime('-3 months');
    $node = $this->createNode([
      'type' => 'page',
      'created' => $old_time,
      'changed' => $old_time,
    ]);
    // Create 3 more old revisions (all older than 1 month).
    for ($i = 0; $i < 3; $i++) {
      $revision = $node_storage->createRevision($node);
      $revision->setChangedTime(strtotime('-2 months +' . $i . ' days'));
      $revision->save();
    }
    // Create a recent revision that is time-protected (2 weeks old).
    $recent_revision = $node_storage->createRevision($node);
    $recent_revision->setChangedTime(strtotime('-2 weeks'));
    $recent_revision->save();
    // Create the active revision.
    $active = $node_storage->createRevision($node);
    $active->save();

    // 6 total: 1 original + 3 old + 1 recent + 1 active.
    $this->assertRevisionCount(6, $node);

    $requeue = $this->container->get('queue')->get('node_revision_delete_requeue');

    // Process the queue. Cron loops through bulk batches (2 at a time) until
    // all old revisions are deleted. The recent revision is time-protected by
    // Created so it survives, and a requeue item is created for it.
    $this->runNodeRevisionDeleteQueue();

    // All 4 old revisions deleted. 2 remain: recent (time-protected) + active.
    $this->assertRevisionCount(2, $node);

    // A time-based requeue item should only be created on the final time the
    // node is processed in a bulk batch cycle. With 4 old revisions and a
    // threshold of 2, the node is processed twice.
    $this->assertSame(1, $requeue->numberOfItems());
  }

  /**
   * Tests no requeue when all revisions are protected by both types of plugin.
   *
   * When every revision protected by a time-based plugin is also protected by
   * a non-time-based plugin, no requeue item should be created because the
   * revision will never become exclusively deletable through time alone.
   */
  public function testNoRequeueWhenAllTimeProtectedAlsoNonTimeProtected(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Configure Created (1 month) and Amount (keep 5 revisions).
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 1,
          ],
        ],
        'amount' => [
          'status' => TRUE,
          'settings' => [
            'amount' => 5,
          ],
        ],
      ])
      ->save();

    // Create a node with 4 revisions spread across time. All are within the
    // Amount plugin's keep-5 threshold (including active), so Amount protects
    // all of them.
    $node = $this->createNode([
      'type' => 'page',
      'created' => strtotime('-3 weeks'),
      'changed' => strtotime('-3 weeks'),
    ]);
    for ($i = 2; $i >= 0; $i--) {
      $revision = $node_storage->createRevision($node);
      $revision->setChangedTime(strtotime('-' . $i . ' weeks'));
      $revision->save();
    }

    // 4 revisions total, all within the last 3 weeks. Created protects the
    // 3 older ones (younger than 1 month). Amount protects the newest 4
    // (keep 5 including active). Every time-protected revision is also
    // protected by Amount.
    $this->assertRevisionCount(4, $node);

    $requeue = $this->container->get('queue')->get('node_revision_delete_requeue');
    $this->assertSame(0, $requeue->numberOfItems());

    $this->runNodeRevisionDeleteQueue();

    // Nothing should be deleted (all protected by Amount).
    $this->assertRevisionCount(4, $node);

    // No requeue should exist because every revision that Created protects
    // is also protected by Amount.
    $this->assertSame(0, $requeue->numberOfItems());
  }

  /**
   * Tests time-based requeue with multilingual content.
   *
   * The requeue logic iterates per langcode and calls getTranslation() on the
   * loaded revision. This test verifies that when two translations have
   * different changed times, the delay is calculated using the correct
   * translation's changed time.
   */
  public function testMultilingualTimeBasedRequeue(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Configure Created with a 1-month age threshold.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 1,
          ],
        ],
      ])
      ->save();

    // Create an English node with an old revision (6 weeks ago).
    $old_time = strtotime('-6 weeks');
    $node = $this->createNode([
      'type' => 'page',
      'created' => $old_time,
      'changed' => $old_time,
    ]);

    // Add a Dutch translation at a different time (2 weeks ago). This creates
    // a new revision where both languages have revision_translation_affected.
    $translation = $node->addTranslation('nl', ['title' => 'Dutch title']);
    $translation->setChangedTime(strtotime('-2 weeks'));
    $new_revision = $node_storage->createRevision($translation);
    $new_revision->save();

    // Create a new English-only revision (now) to make the old English
    // revision older than the active English revision.
    $en_revision = $node_storage->createRevision($node);
    $en_revision->save();

    // Create a new Dutch-only revision (now) to make the old Dutch
    // revision older than the active Dutch revision.
    $node = $node_storage->load($node->id());
    $nl_revision = $node_storage->createRevision($node->getTranslation('nl'));
    $nl_revision->save();

    $requeue = $this->container->get('queue')->get('node_revision_delete_requeue');
    $this->assertSame(0, $requeue->numberOfItems());

    // Process the queue. The 6-week-old English revision is older than 1 month
    // so it is deleted. The 2-week-old Dutch revision is protected by Created
    // (younger than 1 month) and a requeue item should be created.
    $this->runNodeRevisionDeleteQueue();

    $this->assertSame(1, $requeue->numberOfItems());

    // Verify the delay is based on the Dutch translation's changed time
    // (2 weeks ago + 1 month), not the English translation's changed time.
    $item = $this->container->get('database')
      ->select('queue', 'q')
      ->fields('q', ['data'])
      ->condition('name', 'node_revision_delete_requeue')
      ->execute()
      ->fetchField();
    $data = unserialize($item);
    $requeue_time = $data['requeue_time'];

    // The Dutch translation was changed 2 weeks ago. With a 1-month age, the
    // delay should be approximately 2 weeks (1 month - 2 weeks elapsed).
    $expected_delay = strtotime('+1 months', strtotime('-2 weeks')) - time();
    $actual_delay = $requeue_time - time();

    $this->assertEqualsWithDelta($expected_delay, $actual_delay, 5, 'Requeue delay should be based on the Dutch translation changed time.');
    $this->assertGreaterThan(0, $actual_delay);
    $this->assertLessThan(60 * 60 * 24 * 21, $actual_delay, 'Delay should be less than 3 weeks.');
  }

  /**
   * Test the node revision delete for multilingual content.
   */
  public function testNodeRevisionDeleteMultiLingual(): void {
    // Additionally test requeuing for multilingual content.
    $this->config('node_revision_delete.settings')->set('bulk_delete_threshold', 1)->save();
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

    // Create 10 revisions.
    $node = $this->createNode(['type' => 'page']);
    for ($i = 0; $i < 10; $i++) {
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }
    $this->runNodeRevisionDeleteQueue();

    // Assert that only 5 revisions remain.
    $this->assertRevisionCount(5, $node);

    // Translate the node to Dutch and create 10 more revisions.
    $translation = $node->addTranslation('nl', ['title' => 'Dutch title']);
    for ($i = 0; $i < 10; $i++) {
      $new_revision = $node_storage->createRevision($translation);
      $new_revision->save();
    }
    $this->runNodeRevisionDeleteQueue();

    // Assert that 10 revisions remain. There should be 5 Dutch revisions, and 5
    // English revisions.
    $this->assertRevisionCount(10, $node);
  }

  /**
   * Test that revisions shared with another language are not deleted.
   *
   * When an untranslatable field is changed while adding a translation, both
   * languages have revision_translation_affected = 1 on that revision. Node
   * revision delete plugins should not delete that revision if it is still the
   * only affected revision for the other language.
   */
  public function testNodeRevisionDeleteMultiLingualUntranslatableField(): void {
    // Add the German language. Note it is important that this language comes
    // before the English language alphabetically.
    ConfigurableLanguage::createFromLangcode('de')->save();
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Add a non-translatable field to the page content type.
    FieldStorageConfig::create([
      'field_name' => 'field_untranslatable',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_untranslatable',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Untranslatable field',
      'translatable' => FALSE,
    ])->save();

    // Configure the amount plugin to keep 5 revisions.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'amount' => [
          'status' => TRUE,
          'settings' => ['amount' => 5],
        ],
      ])
      ->save();

    // Step 1: Create a node in EN language (revision_translation_affected for
    // EN = 1).
    $node = $this->createNode([
      'type' => 'page',
      'langcode' => 'en',
      'field_untranslatable' => 'initial value',
    ]);

    // Step 2: Add DE translation while changing the untranslatable field. This
    // results in revision_translation_affected = 1 for both EN and DE.
    $translation = $node->addTranslation('de', ['title' => 'German title']);
    $translation->set('field_untranslatable', 'changed value');
    $new_revision = $node_storage->createRevision($translation);
    $new_revision->save();
    $de_revision_id = $new_revision->getRevisionId();

    // Step 3: Create more EN revisions so the 2nd revision (with the DE
    // translation) exceeds the configured amount of revisions to keep for EN.
    for ($i = 0; $i < 6; $i++) {
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }
    $this->runNodeRevisionDeleteQueue();

    // Step 4: Assert the DE translation revision was not deleted. Even though
    // the EN amount limit causes older EN revisions to be pruned, the revision
    // that is the only DE-affected revision must be preserved.
    $de_revision = $node_storage->loadRevision($de_revision_id);
    $this->assertNotNull($de_revision, 'The revision containing the DE translation must not be deleted.');
  }

}
