<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\trash\Handler\DefaultTrashHandler;
use Drupal\trash\Trash;
use Drupal\trash_test\Entity\TrashTestEntity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the crash and data-integrity guards in TrashStorageTrait.
 */
#[Group('trash')]
#[RunTestsInSeparateProcesses]
class TrashStorageTraitTest extends TrashKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected bool $installNode = FALSE;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // Register a handler that tests can arm to fail after the restore save,
    // once the entity caches hold the uncommitted restored state.
    $container->register('trash_storage_trait_test.failing_handler', TrashStorageTraitTestFailingHandler::class)
      ->addTag('trash_handler', ['entity_type_id' => 'trash_test_entity']);
  }

  /**
   * Deleting an already-trashed entity keeps its deletion timestamp.
   */
  public function testRepeatedDeleteKeepsDeletionTime(): void {
    $storage = $this->getEntityTypeManager()->getStorage('trash_test_entity');
    assert($storage instanceof ContentEntityStorageInterface);

    $entity = TrashTestEntity::create(['label' => 'Test']);
    $entity->save();
    $id = $entity->id();
    $entity->delete();

    // Backdate the deletion timestamp so it differs from the request time a
    // repeated delete would stamp.
    $backdated = \Drupal::time()->getRequestTime() - 1000;
    $this->getTrashManager()->executeInTrashContext('ignore', function () use ($storage, $id, $backdated): void {
      $entity = $storage->loadUnchanged($id);
      assert($entity instanceof ContentEntityInterface);
      $entity->set('deleted', $backdated);
      $entity->save();
    });

    // Delete the trashed entity again; it must be left untouched, keeping
    // its auto-purge window and the timestamp seen by restore integrations.
    $trashed = $this->loadTrashedEntity('trash_test_entity', $id);
    $revision_id = $trashed->getRevisionId();
    $storage->delete([$trashed]);

    $reloaded = $this->loadTrashedEntity('trash_test_entity', $id);
    $this->assertEquals($backdated, $reloaded->get('deleted')->value);
    $this->assertEquals($revision_id, $reloaded->getRevisionId());

    // Restore the entity, then restore it again: restoring an entity that is
    // not trashed is a no-op instead of handing a NULL deleted timestamp to
    // the trash handlers.
    $storage->restoreFromTrash([$reloaded]);
    $live = $storage->loadUnchanged($id);
    $this->assertFalse(Trash::entityIsDeleted($live));
    $storage->restoreFromTrash([$live]);
    $this->assertFalse(Trash::entityIsDeleted($storage->loadUnchanged($id)));

    // Delete the restored entity through the stale object that still carries
    // the old deleted timestamp: the stored state is live, so the delete must
    // trash the entity again instead of silently skipping it.
    $storage->delete([$trashed]);
    $this->assertNull($storage->load($id));
    $re_trashed = $this->loadTrashedEntity('trash_test_entity', $id);
    $this->assertEquals(\Drupal::time()->getRequestTime(), $re_trashed->get('deleted')->value);
  }

  /**
   * Restoring a batch skips entities that no longer exist.
   */
  public function testRestoreSkipsPurgedEntity(): void {
    $storage = $this->getEntityTypeManager()->getStorage('trash_test_entity');
    assert($storage instanceof ContentEntityStorageInterface);

    $first = TrashTestEntity::create(['label' => 'First']);
    $first->save();
    $second = TrashTestEntity::create(['label' => 'Second']);
    $second->save();
    $storage->delete([$first, $second]);

    // Keep a stale object for the first entity, then purge it.
    $stale_first = $this->forceLoadEntity($storage, $first->id());
    $this->purgeEntity('trash_test_entity', $first->id());

    // The purged entity is skipped and the rest of the batch is restored.
    $second = $this->forceLoadEntity($storage, $second->id());
    $storage->restoreFromTrash([$stale_first, $second]);

    $this->assertNull($storage->load($first->id()));
    $restored = $storage->load($second->id());
    $this->assertNotNull($restored);
    $this->assertFalse(Trash::entityIsDeleted($restored));
  }

  /**
   * A failed restore rolls back the transaction and resets entity caches.
   */
  public function testRestoreRollbackResetsCaches(): void {
    $storage = $this->getEntityTypeManager()->getStorage('trash_test_entity');
    assert($storage instanceof ContentEntityStorageInterface);

    $entity = TrashTestEntity::create(['label' => 'Test']);
    $entity->save();
    $id = $entity->id();
    $entity->delete();

    // Arm the failing handler registered in register().
    TrashStorageTraitTestFailingHandler::$failure = new \RuntimeException('Restore failed after save.');

    $entity = $this->loadTrashedEntity('trash_test_entity', $id);
    try {
      $storage->restoreFromTrash([$entity]);
      $this->fail('The handler exception must be rethrown.');
    }
    catch (\RuntimeException) {
      // Expected.
    }

    // The rollback resets the entity caches, so the entity is still trashed
    // everywhere: in the persistent cache as well as in SQL queries.
    $this->assertFalse(\Drupal::cache('entity')->get("values:trash_test_entity:$id"));
    $this->assertNull($storage->load($id));
    $reloaded = $this->loadTrashedEntity('trash_test_entity', $id);
    $this->assertTrue(Trash::entityIsDeleted($reloaded));

    // An \Error (e.g. a TypeError in a handler or a hook implementation) must
    // roll back the same way; catching only \Exception would let the
    // transaction commit the half-restored state on stack unwind.
    TrashStorageTraitTestFailingHandler::$failure = new \Error('Restore failed after save.');
    $entity = $this->loadTrashedEntity('trash_test_entity', $id);
    try {
      $storage->restoreFromTrash([$entity]);
      $this->fail('The handler error must be rethrown.');
    }
    catch (\Error) {
      // Expected.
    }

    $this->assertFalse(\Drupal::cache('entity')->get("values:trash_test_entity:$id"));
    $this->assertNull($storage->load($id));
    $reloaded = $this->loadTrashedEntity('trash_test_entity', $id);
    $this->assertTrue(Trash::entityIsDeleted($reloaded));
  }

}

/**
 * Trash handler that fails after the restore save.
 */
class TrashStorageTraitTestFailingHandler extends DefaultTrashHandler {

  /**
   * The throwable postTrashRestore() throws after the restore save.
   *
   * The handler stays inert until a test arms it.
   */
  public static ?\Throwable $failure = NULL;

  /**
   * {@inheritdoc}
   */
  public function postTrashRestore(EntityInterface $entity, int|string $deleted_timestamp): void {
    if (static::$failure) {
      // Put the uncommitted restored state into the entity caches.
      $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
      throw static::$failure;
    }
  }

}
