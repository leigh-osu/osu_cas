<?php

declare(strict_types=1);

namespace Drupal\Tests\node_revision_delete\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drupal\node_revision_delete\NodeRevisionDeleteInterface;
use Drupal\node_revision_delete\Plugin\NodeRevisionDeleteQueryInterface;
use Drupal\Tests\node_revision_delete\Traits\NodeRevisionDeleteTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests backward compatibility with legacy NodeRevisionDeleteInterface plugins.
 *
 * Verifies that plugins implementing only NodeRevisionDeleteInterface (not
 * NodeRevisionDeleteQueryInterface) still work correctly, both in isolation and
 * in conjunction with plugins that implement the new query interface.
 */
#[Group('node_revision_delete')]
#[RunTestsInSeparateProcesses]
class NodeRevisionDeleteLegacyTest extends KernelTestBase {
  use NodeRevisionDeleteTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'field',
    'filter',
    'language',
    'node',
    'node_revision_delete',
    'node_revision_delete_legacy_test',
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
      'node_revision_delete',
    ]);

    // Add the Dutch language.
    ConfigurableLanguage::createFromLangcode('nl')->save();

    // Create a node type that allows revisions.
    $this->createContentType(['type' => 'page', 'revision' => TRUE]);
  }

  /**
   * Tests the legacy plugin working in isolation.
   */
  public function testLegacyPluginAlone(): void {
    // Tests that the legacy plugin does not implement the query interface.
    $plugin_manager = $this->container->get('plugin.manager.node_revision_delete');
    $plugin = $plugin_manager->createInstance('legacy_amount', ['amount' => 5]);
    $this->assertNotInstanceOf(NodeRevisionDeleteQueryInterface::class, $plugin);

    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Configure only the legacy plugin, keeping 5 revisions.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'legacy_amount' => [
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
    $this->assertRevisionCount(11, $node);

    $this->runNodeRevisionDeleteQueue();

    // The legacy plugin should keep 5 revisions.
    $this->assertRevisionCount(5, $node);
  }

  /**
   * Tests a legacy plugin working alongside a query-interface plugin.
   *
   * The legacy plugin (legacy_amount) protects 3 revisions. The modern plugin
   * (created) wants to delete revisions older than 1 month. The legacy plugin's
   * protection should be respected by the system even though the modern plugin
   * wants to delete those revisions.
   */
  public function testLegacyPluginWithQueryPlugin(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Enable both: legacy_amount keeps 3 revisions, created deletes after
    // 1 month.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'legacy_amount' => [
          'status' => TRUE,
          'settings' => [
            'amount' => 3,
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

    // Create 8 revisions, all older than 1 month (so "created" wants to
    // delete all of them).
    $node = $this->createNode([
      'type' => 'page',
      'created' => strtotime('-9 months'),
      'changed' => strtotime('-9 months'),
    ]);
    for ($i = 8; $i >= 1; $i--) {
      $node->setChangedTime(strtotime('-' . $i . ' months'));
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }

    // Make the latest revision recent so it becomes the active revision that
    // is never deleted.
    $node->setChangedTime(time());
    $new_revision = $node_storage->createRevision($node);
    $new_revision->save();

    // We now have 10 revisions: 9 old + 1 current.
    $this->assertRevisionCount(10, $node);

    $this->runNodeRevisionDeleteQueue();

    // The "created" plugin wants to delete all 9 old revisions (older than
    // 1 month). But "legacy_amount" with amount=3 protects the 2 newest old
    // revisions (amount - 1 = 2 before active). So we should have:
    // - 1 active revision (always kept)
    // - 2 protected old revisions (legacy_amount keeps 2 before active)
    // = 3 total.
    $this->assertRevisionCount(3, $node);
  }

  /**
   * Tests that the modern query plugin can delete what the legacy plugin allows.
   *
   * When a legacy plugin returns NULL (no opinion) for some revisions, the
   * modern plugin's delete decisions should take effect.
   */
  public function testQueryPluginDeletesWhenLegacyHasNoOpinion(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // legacy_amount with amount=1 means keep only the active revision — it
    // returns TRUE for all older revisions and has no opinion on newer ones.
    // The "created" plugin deletes revisions older than 2 months.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'legacy_amount' => [
          'status' => TRUE,
          'settings' => [
            'amount' => 1,
          ],
        ],
        'created' => [
          'status' => TRUE,
          'settings' => [
            'age' => 2,
          ],
        ],
      ])
      ->save();

    // Create 6 revisions: 4 old ones (older than 2 months), 1 recent, and
    // 1 current.
    $node = $this->createNode([
      'type' => 'page',
      'created' => strtotime('-6 months'),
      'changed' => strtotime('-6 months'),
    ]);
    for ($i = 5; $i >= 3; $i--) {
      $node->setChangedTime(strtotime('-' . $i . ' months'));
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }
    // One revision within the 2-month window.
    $node->setChangedTime(strtotime('-1 month'));
    $new_revision = $node_storage->createRevision($node);
    $new_revision->save();

    // Current active revision.
    $node->setChangedTime(time());
    $new_revision = $node_storage->createRevision($node);
    $new_revision->save();

    $this->assertRevisionCount(6, $node);

    $this->runNodeRevisionDeleteQueue();

    // legacy_amount(amount=1) wants to delete all revisions before active.
    // created(age=2) wants to delete the 4 old revisions but protects the
    // 1-month-old revision. Protection from any plugin wins, so the 1-month-old
    // revision is kept: 1 active + 1 protected = 2.
    $this->assertRevisionCount(2, $node);
  }

  /**
   * Tests overriding default config with node-type-specific settings.
   */
  public function testLegacyPluginWithNodeTypeOverride(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Default: keep 5 revisions.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'legacy_amount' => [
          'status' => TRUE,
          'settings' => [
            'amount' => 5,
          ],
        ],
      ])
      ->save();

    $node = $this->createNode(['type' => 'page']);
    for ($i = 0; $i < 10; $i++) {
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }
    $this->runNodeRevisionDeleteQueue();
    $this->assertRevisionCount(5, $node);

    // Override for page type: keep only 2 revisions.
    $node_type = $this->container->get('entity_type.manager')->getStorage('node_type')->load('page');
    $node_type->setThirdPartySetting('node_revision_delete', 'legacy_amount', [
      'status' => TRUE,
      'settings' => [
        'amount' => 2,
      ],
    ]);
    $node_type->save();

    $new_revision = $node_storage->createRevision($node);
    $new_revision->save();
    $this->runNodeRevisionDeleteQueue();

    $this->assertRevisionCount(2, $node);
  }

  /**
   * Tests multilingual content with legacy and modern plugins together.
   *
   * Uses the legacy plugin (legacy_amount) to enforce a per-language revision
   * limit alongside the modern query-based plugin (amount) to verify that both
   * interfaces work correctly with translated content and that each language's
   * revisions are managed independently.
   */
  public function testLegacyPluginMultiLingual(): void {
    // Also test requeuing by setting a low bulk delete threshold.
    $this->config('node_revision_delete.settings')->set('bulk_delete_threshold', 1)->save();

    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Enable both: legacy_amount keeps 3 revisions, modern amount keeps 5.
    // The legacy plugin protects 2 revisions before active (amount - 1 = 2),
    // while the modern plugin protects 4 (amount - 1 = 4). Since protection
    // from any plugin is respected, the modern plugin's higher protection wins
    // and each language should retain 5 revisions.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'legacy_amount' => [
          'status' => TRUE,
          'settings' => [
            'amount' => 3,
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

    // Create an English node with 10 revisions.
    $node = $this->createNode(['type' => 'page']);
    for ($i = 0; $i < 10; $i++) {
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }
    $this->runNodeRevisionDeleteQueue();

    // 5 English revisions should remain (modern amount plugin protects more).
    $this->assertRevisionCount(5, $node);

    // Add a Dutch translation and create 10 Dutch revisions.
    $translation = $node->addTranslation('nl', ['title' => 'Dutch title']);
    for ($i = 0; $i < 10; $i++) {
      $new_revision = $node_storage->createRevision($translation);
      $new_revision->save();
    }
    $this->runNodeRevisionDeleteQueue();

    // Each language should have 5 revisions: 10 total.
    $this->assertRevisionCount(10, $node);

    // Now disable the modern amount plugin so only the legacy plugin is active.
    // The legacy plugin keeps 3 revisions per language.
    $this->config('node_revision_delete.settings')
      ->set('defaults', [
        'legacy_amount' => [
          'status' => TRUE,
          'settings' => [
            'amount' => 3,
          ],
        ],
        'amount' => [
          'status' => FALSE,
          'settings' => [
            'amount' => 5,
          ],
        ],
      ])
      ->save();

    // Add one more English revision to trigger queueing.
    $new_revision = $node_storage->createRevision($node);
    $new_revision->save();
    $this->runNodeRevisionDeleteQueue();

    // English: 3 revisions (legacy_amount keeps 3).
    // Dutch: 3 revisions (legacy_amount keeps 3).
    // = 6 total.
    $this->assertRevisionCount(6, $node);
  }

  /**
   * Test getPreviousRevisions().
   *
   * @group legacy
   */
  public function testGetPreviousRevisions(): void {
    if (floatval(\Drupal::VERSION) < 11) {
      $this->expectDeprecation('Drupal\node_revision_delete\NodeRevisionDelete::getPreviousRevisions() is deprecated in node_revision_delete:2.1.0 and is removed from node_revision_delete:3.0.0. Use getPreviousRevisionIds() instead. See https://www.drupal.org/node/3584874');
    }
    else {
      $this->expectUserDeprecationMessage('Drupal\node_revision_delete\NodeRevisionDelete::getPreviousRevisions() is deprecated in node_revision_delete:2.1.0 and is removed from node_revision_delete:3.0.0. Use getPreviousRevisionIds() instead. See https://www.drupal.org/node/3584874');
    }
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')
      ->getStorage('node');
    /** @var \Drupal\node_revision_delete\NodeRevisionDeleteInterface $service */
    $service = $this->container->get('node_revision_delete');

    // Create a node with several English revisions.
    $node = $this->createNode(['type' => 'page', 'title' => 'English v1']);
    for ($i = 2; $i <= 5; $i++) {
      $node->setTitle('English v' . $i);
      $node = $node_storage->createRevision($node);
      $node->save();
    }
    // Add a Dutch translation and create revisions for it.
    $translation = $node->addTranslation('nl', ['title' => 'Dutch v1']);
    $new_revision = $node_storage->createRevision($translation);
    $new_revision->save();
    for ($i = 2; $i <= 3; $i++) {
      $translation = $node_storage->load($node->id())->getTranslation('nl');
      $translation->setTitle('Dutch v' . $i);
      $new_revision = $node_storage->createRevision($translation);
      $new_revision->save();
    }

    $this->assertSame(
      array_map(fn (NodeInterface $revision) => (int) $revision->getRevisionId(), $service->getPreviousRevisions((int) $node->id(), (int) $node->getRevisionId(), 'en')),
      $service->getPreviousRevisionIds((int) $node->id(), (int) $node->getRevisionId(), 'en')
    );

    $this->assertSame(
      array_map(fn (NodeInterface $revision) => (int) $revision->getRevisionId(), $service->getPreviousRevisions((int) $node->id(), (int) $node->getRevisionId(), 'nl')),
      $service->getPreviousRevisionIds((int) $node->id(), (int) $node->getRevisionId(), 'nl')
    );
  }

}
