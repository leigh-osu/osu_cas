<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\node\Entity\Node;
use Drupal\trash\Trash;
use Drupal\trash_test\Entity\TrashTestEntity;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests basic trash functionality.
 */
#[Group('trash')]
#[RunTestsInSeparateProcesses]
class TrashIntegrationTest extends TrashKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'image',
    'media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
  }

  /**
   * Test trashing entities.
   */
  public function testDeletion(): void {
    $entity = TrashTestEntity::create();
    assert($entity instanceof ContentEntityInterface);
    $entity->save();
    $entity_id = $entity->id();

    $this->assertNotEmpty(TrashTestEntity::load($entity_id));
    $this->assertFalse(Trash::entityIsDeleted($entity));
    $this->assertTrue($entity->get('deleted')->isEmpty());
    $this->assertNull($entity->get('deleted')->value);

    $entity->delete();

    // Test the default 'active' trash context.
    $this->assertEmpty(TrashTestEntity::load($entity_id), 'Deleted entities can not be loaded in the default (active) trash context.');

    // Test the 'ignore' trash context.
    $entity = $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_id) {
      return TrashTestEntity::load($entity_id);
    });
    assert($entity instanceof ContentEntityInterface);
    $this->assertNotEmpty($entity, 'Deleted entities can still be loaded in the "ignore" trash context.');
    $this->assertTrue(Trash::entityIsDeleted($entity));
    $this->assertEquals(\Drupal::time()->getRequestTime(), $entity->get('deleted')->value);

    // Deleting an already-trashed entity again is a no-op: the reload in the
    // active trash context cannot see it, so it is skipped instead of
    // crashing, and no new deletion revision is created.
    $revision_count = $this->countAllRevisions('trash_test_entity', $entity_id);
    $entity->delete();
    $this->assertSame($revision_count, $this->countAllRevisions('trash_test_entity', $entity_id));

    $second_entity = TrashTestEntity::create();
    $second_entity->save();
    $second_entity_id = $second_entity->id();

    // Test the default 'active' trash context for multiple load.
    $entities = TrashTestEntity::loadMultiple();
    $this->assertCount(1, $entities);
    $this->assertEquals($second_entity_id, $entities[$second_entity_id]->id());

    // Test the 'ignore' trash context  for multiple load.
    $entities = $this->getTrashManager()->executeInTrashContext('ignore', function () {
      return TrashTestEntity::loadMultiple();
    });
    $this->assertCount(2, $entities);
    $this->assertEquals($entity_id, $entities[$entity_id]->id());
    $this->assertEquals($second_entity_id, $entities[$second_entity_id]->id());

    // A batch delete that contains an already-trashed entity skips it and
    // still processes the remaining entities.
    $storage = $this->getEntityTypeManager()->getStorage('trash_test_entity');
    $storage->delete([$entity, $entities[$second_entity_id]]);
    $this->assertEmpty(TrashTestEntity::load($second_entity_id));
    $second_entity = $this->loadTrashedEntity('trash_test_entity', $second_entity_id);
    $this->assertTrue(Trash::entityIsDeleted($second_entity));
  }

  /**
   * Test prevention of stale entities in the caches.
   */
  public function testPersistentCache(): void {
    $live_node = $this->createNode(['type' => 'article']);
    $live_node->save();

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('node');
    $nid = $live_node->id();
    $loaded = $storage->load($nid);

    // Sanity checks that caches work.
    $this->assertNotEmpty($loaded);
    $this->assertSame(1, $storage->getLatestRevisionId($nid));
    $this->assertEquals(1, $storage->getLatestTranslationAffectedRevisionId($nid, 'en'));

    $loaded->delete();

    // Deleting an entity should reset the in-memory and persistent entity
    // caches per ids [and revisions in D11].
    $this->assertEmpty($storage->load($nid));
    $this->assertNull($storage->getLatestRevisionId($nid));
    // @todo This returns the pre-deleted revision, looks like a bug.
    $this->assertSame(1, $storage->getLatestTranslationAffectedRevisionId($nid, 'en'));

    // Deactivate the Trash context and fill the caches.
    $this->getTrashManager()->setTrashContext('inactive');
    $this->assertNotEmpty($storage->load($nid));
    $this->assertSame(2, $storage->getLatestRevisionId($nid));
    $this->assertSame(2, $storage->getLatestTranslationAffectedRevisionId($nid, 'en'));

    // Re-activate the caches and ensure the caches have been cleared.
    $this->getTrashManager()->setTrashContext('active');
    $this->assertEmpty($storage->load($nid));
    $this->assertNull($storage->getLatestRevisionId($nid));
    // @todo This returns the pre-deleted revision, looks like a bug.
    $this->assertSame(1, $storage->getLatestTranslationAffectedRevisionId($nid, 'en'));

    // Repeat the above with executeInTrashContext().
    $this->getTrashManager()->executeInTrashContext('inactive', function () use ($storage, $nid) {
      $this->assertNotEmpty($storage->load($nid));
      $this->assertSame(2, $storage->getLatestRevisionId($nid));
      $this->assertSame(
        2,
        $storage->getLatestTranslationAffectedRevisionId($nid, 'en')
      );
    });

    // Re-activate the caches and ensure the caches have been cleared.
    $this->assertEmpty($storage->load($nid));
    $this->assertNull($storage->getLatestRevisionId($nid));
    // @todo This returns the pre-deleted revision, looks like a bug.
    $this->assertSame(1, $storage->getLatestTranslationAffectedRevisionId($nid, 'en'));
  }

  /**
   * Tests Trash::entityIsDeleted() for disabled entity type.
   */
  public function testDisabledEntityType(): void {
    // Check a node bundle that's not trash-enabled.
    $nonDeletableNode = $this->createNode(['type' => 'page']);
    $nonDeletableNode->save();
    $this->assertFalse(Trash::entityIsDeleted($nonDeletableNode));

    $nonDeletableNode->delete();
    $this->assertEmpty(Node::load($nonDeletableNode->id()));

    // Check an entity type that's not trash-enabled.
    $nonDeletableEntity = $this->createUser(['administer users']);
    $nonDeletableEntity->save();
    $this->assertFalse(Trash::entityIsDeleted($nonDeletableEntity));
    $this->assertFalse($nonDeletableEntity->access('restore', $nonDeletableEntity));
    $this->assertFalse($nonDeletableEntity->access('purge', $nonDeletableEntity));

    $nonDeletableEntity->delete();
    $this->assertEmpty(User::load($nonDeletableEntity->id()));
  }

  /**
   * @covers \Drupal\trash\TrashManager::isEntityTypeEnabled
   *
   * @dataProvider providerIsEntityTypeEnabled
   */
  public function testIsEntityTypeEnabled($entity_type_id, $bundle, $enabled): void {
    $this->assertSame($enabled, $this->getTrashManager()->isEntityTypeEnabled($entity_type_id, $bundle));
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $this->assertSame($enabled, $this->getTrashManager()->isEntityTypeEnabled($entity_type, $bundle));
  }

  /**
   * Data provider for testIsEntityTypeEnabled().
   */
  public static function providerIsEntityTypeEnabled(): array {
    return [
      // An entity type with a single bundle enabled.
      ['node', NULL, TRUE],
      ['node', 'article', TRUE],
      ['node', 'page', FALSE],
      // An entity type with all bundles enabled.
      ['trash_test_entity', NULL, TRUE],
      ['trash_test_entity', 'random_bundle_id', TRUE],
      // A disabled entity type.
      ['media', NULL, FALSE],
      ['media', 'random_bundle_id', FALSE],
    ];
  }

  /**
   * Tests that the trash context is appropriately switched based on the user.
   */
  public function testTrashContextSwitching(): void {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = $this->container->get('account_switcher');
    $administer_trash_user = $this->createUser(['administer trash']);
    $access_trash_user = $this->createUser(['access trash']);
    $view_deleted_trash_user = $this->createUser(['view deleted entities']);
    $normal_user = $this->createUser();

    // The trash context should be 'active' by default.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // The trash context should stay the same.
    $account_switcher->switchTo($administer_trash_user);
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // The trash context should stay the same.
    $account_switcher->switchTo($access_trash_user);
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // The trash context should stay the same.
    $account_switcher->switchTo($view_deleted_trash_user);
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // The trash context should be switched if the "in_trash" query string is
    // set.
    \Drupal::request()->query->set('in_trash', '1');
    $account_switcher->switchTo($administer_trash_user);
    static::assertEquals('ignore', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    // The trash context should switch back to the 'active' state.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    $account_switcher->switchTo($access_trash_user);
    static::assertEquals('ignore', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    // The trash context should switch back to the 'active' state.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    $account_switcher->switchTo($view_deleted_trash_user);
    static::assertEquals('ignore', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    // The trash context should switch back to the 'active' state.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // The state should still be active as the user does not have the necessary
    // permissions.
    $account_switcher->switchTo($normal_user);
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // Assert that if the trash context is switched to 'ignore' even if it was
    // previously 'inactive' as the "in_trash" query string takes precedence.
    $this->getTrashManager()->setTrashContext('inactive');
    $account_switcher->switchTo($administer_trash_user);
    static::assertEquals('ignore', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    // The trash context should switch back to the 'active' state.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // Assert that the trash context is now 'active' even if it was previously
    // 'inactive'.
    $this->getTrashManager()->setTrashContext('inactive');
    $account_switcher->switchTo($normal_user);
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    // Should now be active.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
  }

  /**
   * Tests that deleted entities are not stored in cache.
   */
  public function testDeletedEntitiesNotInCache(): void {
    $entity = TrashTestEntity::create();
    $entity->save();
    $entity_id = $entity->id();

    // Load the entity to ensure it gets cached.
    $storage = $this->getEntityTypeManager()->getStorage('trash_test_entity');
    $storage->resetCache();
    TrashTestEntity::load($entity_id);

    // Verify the entity is in the persistent cache.
    $persistent_cache = \Drupal::cache('entity');
    $cid = "values:trash_test_entity:$entity_id";
    $this->assertNotNull($persistent_cache->get($cid));

    // Verify the entity is in the memory cache.
    $memory_cache = \Drupal::service('entity.memory_cache');
    $this->assertNotNull($memory_cache->get($cid));

    // Delete the entity (soft-delete).
    $entity->delete();

    // The cache entries should be deleted after the entity is soft-deleted.
    $this->assertFalse($persistent_cache->get($cid));
    $this->assertFalse($memory_cache->get($cid));

    // Load the deleted entity in the 'ignore' trash context and verify caches
    // while still inside the context.
    $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_id, $persistent_cache, $memory_cache, $cid) {
      TrashTestEntity::load($entity_id);

      // Verify the deleted entity is not in the persistent cache.
      $this->assertFalse($persistent_cache->get($cid));

      // Verify the deleted entity is not in the memory cache.
      $this->assertFalse($memory_cache->get($cid));
    });
  }

  /**
   * Tests getting the latest revision ID while switching trash contexts.
   */
  public function testGetLatestRevisionId(): void {
    $storage = $this->getEntityTypeManager()->getStorage('trash_test_entity');
    assert($storage instanceof ContentEntityStorageInterface);

    // Create a test entity (revision 1).
    $entity = $storage->create(['label' => 'Test entity']);
    assert($entity instanceof ContentEntityInterface);
    $entity->save();
    $default_revision_id = $entity->getRevisionId();

    // Create a pending revision (revision 2).
    $entity->setNewRevision(TRUE);
    $entity->isDefaultRevision(FALSE);
    $entity->save();
    $pending_revision_id = $entity->getRevisionId();

    // Soft-delete the entity (revision 3). delete() acts on a freshly loaded
    // default revision, so the deletion revision has to be read from a
    // reloaded entity, not the passed object.
    $storage->delete([$entity]);
    $entity = $this->forceLoadEntity($storage, $entity->id());
    $deleted_revision_id = $entity->getRevisionId();

    // Verify we have 3 distinct revisions.
    $this->assertEquals([1, 2, 3], [$default_revision_id, $pending_revision_id, $deleted_revision_id]);

    // Verify the correct value in the 'active' context after deletion.
    $this->assertEquals('active', $this->getTrashManager()->getTrashContext());
    $this->assertNull($storage->getLatestRevisionId($entity->id()));

    // Switch to the 'ignore' context and call getLatestRevisionId().
    $this->getTrashManager()->setTrashContext('ignore');
    $this->assertEquals($deleted_revision_id, $storage->getLatestRevisionId($entity->id()));

    // Switch back to the 'active' context.
    $this->getTrashManager()->setTrashContext('active');
    $this->assertNull($storage->getLatestRevisionId($entity->id()));

    // Restoring creates a new revision (revision 4).
    $this->getTrashManager()->setTrashContext('ignore');
    $storage->restoreFromTrash([$entity]);
    $entity = $this->forceLoadEntity($storage, $entity->id());
    $restored_revision_id = $entity->getRevisionId();
    $this->assertGreaterThan($deleted_revision_id, $restored_revision_id);

    // Restoring again un-deletes nothing, so it must not create a revision.
    $storage->restoreFromTrash([$entity]);
    $reloaded = $this->forceLoadEntity($storage, $entity->id());
    $this->assertSame($restored_revision_id, $reloaded->getRevisionId());
  }

  /**
   * Counts all revisions of an entity, ignoring trash state.
   */
  private function countAllRevisions(string $entity_type_id, string|int $id): int {
    return (int) $this->getEntityTypeManager()->getStorage($entity_type_id)->getQuery()
      ->addMetaData('trash', 'ignore')
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('id', $id)
      ->count()
      ->execute();
  }

  /**
   * Tests that hook_modules_installed preserves existing enabled_entity_types.
   */
  public function testModulesInstalledPreservesConfig(): void {
    // TrashKernelTestBase::setUp() seeds enabled_entity_types.node with a
    // specific bundle list, mimicking a distribution/profile that ships a
    // pre-configured trash.settings. Firing hook_modules_installed (as
    // happens at the end of a profile install) must not overwrite that
    // bundle list with an empty array.
    $before = \Drupal::config('trash.settings')->get('enabled_entity_types');
    $this->assertSame(['article'], $before['node']);

    \Drupal::moduleHandler()->invokeAll('modules_installed', [['node'], FALSE]);

    $after = \Drupal::config('trash.settings')->get('enabled_entity_types');
    $this->assertSame(['article'], $after['node']);

    // Nothing may change while config is syncing.
    \Drupal::moduleHandler()->invokeAll('modules_installed', [['path_alias'], TRUE]);
    $after = \Drupal::config('trash.settings')->get('enabled_entity_types');
    $this->assertArrayNotHasKey('path_alias', $after);

    // Each supported entity type is enabled independently: installing
    // path_alias or menu_link_content on their own, after trash and node are
    // already installed, must add them without touching the node config.
    \Drupal::moduleHandler()->invokeAll('modules_installed', [['path_alias'], FALSE]);
    $after = \Drupal::config('trash.settings')->get('enabled_entity_types');
    $this->assertSame([], $after['path_alias']);
    $this->assertSame(['article'], $after['node']);

    \Drupal::moduleHandler()->invokeAll('modules_installed', [['menu_link_content'], FALSE]);
    $after = \Drupal::config('trash.settings')->get('enabled_entity_types');
    $this->assertSame([], $after['menu_link_content']);
    $this->assertSame([], $after['path_alias']);
    $this->assertSame(['article'], $after['node']);
  }

  /**
   * Tests delete and restore in the request that enables an entity type.
   */
  public function testDeleteAndRestoreWithoutHandler(): void {
    // Enable an entity type by saving config, without the container rebuild
    // that registers its trash handler: this is the state for the rest of the
    // request that enables an entity type, since handlers are wired to the
    // trash manager at compile time.
    $config = \Drupal::configFactory()->getEditable('trash.settings');
    $enabled = $config->get('enabled_entity_types');
    $enabled['user'] = [];
    $config->set('enabled_entity_types', $enabled)->save();

    $this->assertTrue($this->getTrashManager()->isEntityTypeEnabled('user'));
    $this->assertNull($this->getTrashManager()->getHandler('user'));

    // Deleting and restoring must work without a registered handler.
    $account = $this->createUser();
    $account->delete();

    $storage = $this->getEntityTypeManager()->getStorage('user');
    $this->assertNull($storage->load($account->id()));
    $trashed = $this->loadTrashedEntity('user', $account->id());
    $this->assertNotNull($trashed);
    $this->assertTrue(Trash::entityIsDeleted($trashed));

    $storage->restoreFromTrash([$trashed]);
    $restored = $storage->loadUnchanged($account->id());
    $this->assertNotNull($restored);
    $this->assertFalse(Trash::entityIsDeleted($restored));
  }

  /**
   * Tests detection of trash.settings drifting from the field layer.
   */
  public function testConfigDriftDetection(): void {
    $logger = new TestLogger();
    $this->container->get(LoggerChannelFactoryInterface::class)
      ->get('trash')
      ->addLogger($logger);
    $config = \Drupal::configFactory()->getEditable('trash.settings');
    $enabled = $config->get('enabled_entity_types');

    // Enabling an entity type whose storage cannot support trash must be
    // reported on config save.
    $enabled['foo'] = [];
    $config->set('enabled_entity_types', $enabled)->save();
    $this->assertTrue($logger->hasRecordThatPasses(
      static fn (array $record): bool => ($record['context']['@entity_type_id'] ?? '') === 'foo'
        && str_contains($record['message'], 'storage does not support Trash'),
      RfcLogLevel::ERROR,
    ));
    unset($enabled['foo']);

    // Enabling an entity type whose 'deleted' field belongs to another module
    // must be reported as well: the field cannot be installed over, so every
    // query for that type would filter on the foreign field.
    $foreign_field = BaseFieldDefinition::create('timestamp')
      ->setLabel('Deleted')
      ->setRevisionable(TRUE);
    \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('deleted', 'media', 'media', $foreign_field);
    $enabled['media'] = [];
    $config->set('enabled_entity_types', $enabled)->save();
    $this->assertTrue($logger->hasRecordThatPasses(
      static fn (array $record): bool => ($record['context']['@entity_type_id'] ?? '') === 'media'
        && str_contains($record['message'], "'deleted' field does not belong to Trash"),
      RfcLogLevel::ERROR,
    ));

    // Disabling the entity type must refuse to uninstall the foreign field.
    try {
      $this->getTrashManager()->disableEntityType($this->getEntityTypeManager()->getDefinition('media'));
      $this->fail('Uninstalling a foreign deleted field must throw.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString("does not belong to Trash; refusing to uninstall it", $e->getMessage());
    }
    $field_storage_definitions = \Drupal::service('entity.last_installed_schema.repository')->getLastInstalledFieldStorageDefinitions('media');
    $this->assertArrayHasKey('deleted', $field_storage_definitions);
    $this->assertSame('media', $field_storage_definitions['deleted']->getProvider());
  }

}
