<?php

namespace Drupal\trash;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Provides soft-delete and restore capabilities at the storage level.
 *
 * If the entity is revisionable, it will create a new revision when
 * soft-deleting and restoring.
 *
 * @phpstan-require-implements \Drupal\Core\Entity\ContentEntityStorageInterface
 *
 * @property string $entityTypeId
 * @property \Drupal\Core\Database\Connection $database
 */
trait TrashStorageTrait {

  /**
   * {@inheritdoc}
   */
  public function delete(array $entities) {
    if (!$entities || $this->getTrashManager()->getTrashContext() !== 'active') {
      parent::delete($entities);
      return;
    }

    $to_delete = [];
    $to_trash = [];

    foreach ($entities as $entity) {
      if ($this->getTrashManager()->isEntityTypeEnabled($entity->getEntityType(), $entity->bundle())) {
        $to_trash[$entity->id()] = $entity;
      }
      else {
        $to_delete[] = $entity;
      }
    }

    parent::delete($to_delete);

    if (empty($to_trash)) {
      return;
    }

    $field_name = 'deleted';
    $revisionable = $this->getEntityType()->isRevisionable();
    $current_user_id = $this->getCurrentUser()->id();
    $request_time = $this->getTime()->getRequestTime();

    foreach ($to_trash as $entity) {
      // Reload the default revision so the deletion revision is always based
      // on published content, not a pending revision or a stale object. The
      // reload returns NULL when the entity is not visible in the active
      // trash context (already trashed, for example by a concurrent request)
      // or no longer exists, so there is nothing left to soft-delete and it
      // can be skipped.
      if (!$entity = $this->loadUnchanged($entity->id())) {
        continue;
      }

      // Allow code to run before soft-deleting. The handler can be NULL if no
      // handler is registered yet for this entity type (e.g. trash was just
      // enabled for it in the current request, before the container was
      // rebuilt).
      $this->getTrashManager()->getHandler($this->entityTypeId)?->preTrashDelete($entity);
      $this->invokeHook('pre_trash_delete', $entity);

      $entity->set($field_name, $request_time);

      // Always create a new revision if the entity type is revisionable.
      if ($revisionable) {
        assert($entity instanceof RevisionableInterface);
        $entity->setNewRevision(TRUE);

        if ($entity instanceof RevisionLogInterface) {
          $entity->setRevisionUserId($current_user_id);
          $entity->setRevisionCreationTime($request_time);
          $entity->setRevisionLogMessage(new TranslatableMarkup('Deleted.'));
        }
      }
      $entity->save();

      // Allow code to run after soft-deleting.
      $this->getTrashManager()->getHandler($this->entityTypeId)?->postTrashDelete($entity);
      $this->invokeHook('trash_delete', $entity);
    }
  }

  /**
   * Restores soft-deleted entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entity objects to restore.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures, an exception is thrown.
   */
  public function restoreFromTrash(array $entities) {
    try {
      $transaction = $this->database->startTransaction();
      // Restore inside the 'ignore' context so both the reload below and the
      // original that save() loads internally can see the still-trashed rows;
      // in the active context the storage query filters deleted entities out.
      $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entities) {
        $field_name = 'deleted';
        $revisionable = $this->getEntityType()->isRevisionable();
        $current_user_id = $this->getCurrentUser()->id();
        $request_time = $this->getTime()->getRequestTime();

        foreach ($entities as $entity) {
          // Reload the stored state, so restore does not depend on the passed
          // object being fresh. The reload returns NULL when the entity no
          // longer exists (e.g. it was purged since it was selected for
          // restore), and an entity that is not trashed has nothing to
          // restore; proceeding would hand a NULL deleted timestamp to the
          // trash handlers.
          $entity = $this->loadUnchanged($entity->id());
          if (!$entity || !Trash::entityIsDeleted($entity)) {
            continue;
          }

          // Allow code to run before restoring from trash.
          $this->getTrashManager()->getHandler($this->entityTypeId)?->preTrashRestore($entity);
          $this->invokeHook('pre_trash_restore', $entity);

          $deleted_timestamp = $entity->get($field_name)->value;
          $entity->set($field_name, NULL);

          // Always create a new revision if the entity type is revisionable.
          if ($revisionable) {
            assert($entity instanceof RevisionableInterface);
            $entity->setNewRevision(TRUE);

            if ($entity instanceof RevisionLogInterface) {
              $entity->setRevisionUserId($current_user_id);
              $entity->setRevisionCreationTime($request_time);
              $entity->setRevisionLogMessage(new TranslatableMarkup('Restored from trash'));
            }
          }
          $entity->save();

          // Allow code to run after restoring from trash.
          $this->getTrashManager()->getHandler($this->entityTypeId)?->postTrashRestore($entity, $deleted_timestamp);
          $this->invokeHook('trash_restore', $entity);
        }
      });
    }
    catch (\Throwable $e) {
      // Catch \Throwable, not just \Exception: an \Error here (e.g. a
      // TypeError from a handler or a hook implementation) would otherwise
      // skip the rollback and let the transaction commit on stack unwind.
      if (isset($transaction)) {
        $transaction->rollBack();
        // Each save inside the transaction updated the static and persistent
        // entity caches, and post-restore handlers may have re-populated them
        // with the uncommitted restored state. Drop those entries so they are
        // rebuilt from the rolled-back database state.
        $this->resetCache(array_map(static fn ($entity) => $entity->id(), $entities));
      }
      // Log under the entity type's own channel (e.g. 'node', 'file'), not
      // 'trash', so restore failures land with that entity's other logs.
      Error::logException(\Drupal::logger($this->entityTypeId), $e);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildQuery($ids, $revision_ids = FALSE) {
    $query = parent::buildQuery($ids, $revision_ids);

    if ($this->getTrashManager()->getTrashContext() !== 'active') {
      return $query;
    }

    if (!$revision_ids
      && $this->getWorkspaceInformation()?->isEntityTypeSupported($this->entityType)
      && ($active_workspace = $this->getWorkspaceManager()?->getActiveWorkspace())
    ) {
      // Join the workspace_association table so we can select possible
      // workspace-specific revisions.
      $wa_join = $query->leftJoin('workspace_association', NULL, "[%alias].[target_entity_type_id] = '{$this->entityTypeId}' AND [%alias].[target_entity_id] = [base].[{$this->idKey}] AND [%alias].[workspace] = :active_workspace_id", [
        ':active_workspace_id' => $active_workspace->id(),
      ]);

      // Joins must be in order. i.e, any tables you mention in the ON clause of
      // a JOIN must appear prior to that JOIN. So we must ensure that the new
      // 'workspace_association' table appears prior to the 'revision' one.
      $tables =& $query->getTables();
      $revision = $tables['revision'];
      unset($tables['revision']);
      $tables['revision'] = $revision;

      $tables['revision']['condition'] = "[revision].[{$this->revisionKey}] = COALESCE([$wa_join].[target_entity_revision_id], [base].[{$this->revisionKey}])";
    }

    $table_mapping = $this->getTableMapping();
    $deleted_column = $table_mapping->getFieldColumnName($this->fieldStorageDefinitions['deleted'], 'value');

    // Ensure that entity_load excludes deleted entities.
    if ($revision_data = $this->getRevisionDataTable()) {
      // Use the alias join() actually assigns (via the '%alias' placeholder)
      // rather than assuming the requested 'revision_data' was free, so a
      // collision with another module's join on the same alias can't leave
      // this condition pointing at the wrong table.
      $revision_data_alias = $query->join($revision_data, 'revision_data', "[%alias].[{$this->revisionKey}] = [revision].[{$this->revisionKey}]");
      $query->condition("$revision_data_alias.$deleted_column", NULL, 'IS NULL');
    }
    elseif ($this->getRevisionTable()) {
      $query->condition("revision.$deleted_column", NULL, 'IS NULL');
    }
    elseif ($data_table = $this->getDataTable()) {
      $data_alias = $query->join($data_table, 'data', "[%alias].[{$this->idKey}] = [base].[{$this->idKey}]");
      $query->condition("$data_alias.$deleted_column", NULL, 'IS NULL');
    }
    else {
      $query->condition("base.$deleted_column", NULL, 'IS NULL');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  protected function getStorageSchema() {
    if (!isset($this->storageSchema)) {
      $class = $this->entityType->getHandlerClass('storage_schema') ?: 'Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema';

      // Ensure that we use our generated storage schema class.
      $class = _trash_generate_storage_class($class, 'storage_schema');

      $this->storageSchema = new $class($this->entityTypeManager, $this->entityType, $this, $this->database, $this->entityFieldManager);
    }
    return $this->storageSchema;
  }

  /**
   * Gets the trash manager service.
   */
  private function getTrashManager(): TrashManagerInterface {
    return \Drupal::service('trash.manager');
  }

  /**
   * Gets the workspace manager service.
   */
  private function getWorkspaceManager(): ?WorkspaceManagerInterface {
    return \Drupal::hasService('workspaces.manager') ? \Drupal::service('workspaces.manager') : NULL;
  }

  /**
   * Gets the workspace information service.
   */
  private function getWorkspaceInformation(): ?WorkspaceInformationInterface {
    return \Drupal::hasService('workspaces.information') ? \Drupal::service('workspaces.information') : NULL;
  }

  /**
   * Gets the time service.
   */
  private function getTime(): TimeInterface {
    return \Drupal::time();
  }

  /**
   * Gets the current user.
   */
  private function getCurrentUser(): AccountInterface {
    return \Drupal::currentUser();
  }

}
