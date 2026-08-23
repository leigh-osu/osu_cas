<?php

declare(strict_types=1);

namespace Drupal\trash\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\trash\Trash;
use Drupal\trash\TrashManagerInterface;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Entity hook implementations for Trash.
 */
class TrashEntityHooks {

  use StringTranslationTrait;

  public function __construct(
    protected TrashManagerInterface $trashManager,
    protected ?WorkspaceManagerInterface $workspaceManager = NULL,
    protected ?WorkspaceInformationInterface $workspaceInformation = NULL,
  ) {}

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    $is_trash_operation = in_array($operation, ['restore', 'purge'], TRUE);

    $access = AccessResult::allowed()->addCacheableDependency($entity);

    // Reverting to an old deleted revision would make the entity deleted
    // again, so block it even for users who can otherwise revert.
    if ($operation === 'revert revision'
      && $entity instanceof RevisionableInterface
      && !$entity->isDefaultRevision()
      && !$entity->isLatestRevision()
      && Trash::entityIsDeleted($entity)
    ) {
      return $access->andIf(AccessResult::forbidden('Can not revert to a deleted revision'));
    }

    if ($is_trash_operation) {
      // Restore and purge outcomes flip when trash support is enabled or
      // disabled for the entity type, so they depend on the trash settings.
      $access->addCacheTags(['config:trash.settings']);
      if (!$this->trashManager->isEntityTypeEnabled($entity->getEntityTypeId())) {
        return $access->andIf(AccessResult::forbidden('Unsupported entity type'));
      }
    }

    if (!Trash::entityIsDeleted($entity)) {
      // A non-deleted entity can never be restored or purged. Other operations
      // are left to their own access handlers.
      return $access->andIf(AccessResult::forbiddenIf($is_trash_operation, "Unsupported operation on non-deleted entity: $operation"));
    }

    $is_view_operation = $operation === 'view' || $operation === 'view label';

    // Old deleted revisions can still be managed with the regular revision
    // permissions while the trash context is 'ignore'. The default revision
    // keeps the deleted-entity forbid below in every context, so a trashed
    // entity cannot be edited or deleted just because the context was switched,
    // for example through the 'in_trash' query parameter.
    if (!$is_trash_operation && !$is_view_operation
      && $entity instanceof RevisionableInterface
      && !$entity->isDefaultRevision()
      && $this->trashManager->getTrashContext() === 'ignore'
    ) {
      return $access->andIf(AccessResult::neutral('Trash context is "ignore" for a non-default revision'));
    }

    $access->addCacheContexts(['user.permissions']);

    if ($is_view_operation) {
      // Only ever remove view access here; let the entity's own access handler
      // decide whether to grant it. Returning allowed() would OR with the
      // handler and over-grant on entity types whose handler returns neutral
      // (rather than forbidden) when a user lacks view access.
      return AccessResult::forbiddenIf(!$account->hasPermission('view deleted entities'))
        ->addCacheableDependency($entity)
        ->cachePerPermissions();
    }

    if ($operation === 'restore') {
      $permission = "restore {$entity->getEntityTypeId()} entities";
      if (!$account->hasPermission($permission)) {
        return $access->andIf(AccessResult::forbidden("The '$permission' permission is required."));
      }
      return $access;
    }

    if ($operation === 'purge') {
      $permission = "purge {$entity->getEntityTypeId()} entities";
      if (!$account->hasPermission($permission)) {
        return $access->andIf(AccessResult::forbidden("The '$permission' permission is required."));
      }

      // Ensure that trashed entities can only be purged in the workspace they
      // were created in or in Live.
      if (NULL !== $this->workspaceManager
        && NULL !== $this->workspaceInformation
        && $this->workspaceInformation->isEntitySupported($entity)
        && ($active_workspace = $this->workspaceManager->getActiveWorkspace())
      ) {
        $access->addCacheableDependency($active_workspace);
        if (!$this->workspaceInformation->isEntityDeletable($entity, $active_workspace)) {
          return $access->andIf(AccessResult::forbidden('Entity cannot be purged in the current workspace.'));
        }
      }

      return $access;
    }

    // Block all other operations for deleted entities.
    return $access->andIf(AccessResult::forbidden('Entity is deleted'));
  }

  /**
   * Implements hook_entity_query_alter().
   */
  #[Hook('entity_query_alter')]
  public function entityQueryAlter(QueryInterface $query): void {
    if (!$this->trashManager->shouldAlterQueries() || !$this->trashManager->isEntityTypeEnabled($query->getEntityTypeId())) {
      return;
    }

    $reflected_condition = new \ReflectionProperty($query::class, 'condition');
    $condition_group = $reflected_condition->getValue($query);
    assert($condition_group instanceof ConditionInterface);

    // Skip altering queries with an explicit filter on the 'deleted' field, so
    // you can still list deleted content, if needed.
    $deleted_key = 'deleted';
    if ($this->hasDeletedCondition($condition_group)) {
      return;
    }

    if (!$query->getMetaData('trash')) {
      $query->addMetaData('trash', $this->trashManager->getTrashContext());
    }

    // Skip altering queries if the context has been manually set to ignore.
    if ($query->getMetaData('trash') === 'ignore') {
      return;
    }

    // If the entity query conjunction is 'OR', we need to wrap the original
    // condition group as the first condition of a new AND condition group,
    // otherwise the query would always return all non-deleted entities.
    $reflected_conjunction = new \ReflectionProperty($query::class, 'conjunction');
    if ($reflected_conjunction->getValue($query) === 'OR') {
      $reflected_conjunction->setValue($query, 'AND');

      $new_condition_group = $query->andConditionGroup()
        ->condition($condition_group);
      $reflected_condition->setValue($query, $new_condition_group);
    }

    if ($query->getMetaData('trash') === 'inactive') {
      $query->exists($deleted_key);
    }
    else {
      $query->notExists($deleted_key);
    }
  }

  /**
   * Checks whether a condition group already filters on the 'deleted' field.
   *
   * Recurses into nested AND/OR condition groups (created via
   * andConditionGroup()/orConditionGroup()), and matches both the plain
   * 'deleted' field and property paths like 'deleted.value'.
   */
  protected function hasDeletedCondition(ConditionInterface $condition_group): bool {
    foreach ($condition_group->conditions() as $condition) {
      if ($condition['field'] instanceof ConditionInterface) {
        if ($this->hasDeletedCondition($condition['field'])) {
          return TRUE;
        }
        continue;
      }
      if ($condition['field'] === 'deleted' || str_starts_with((string) $condition['field'], 'deleted.')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Implements hook_entity_operation_alter().
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): void {
    // Skip access checks for non-deleted entities.
    if (!Trash::entityIsDeleted($entity)) {
      return;
    }

    // Remove all other operations for deleted entities.
    $operations = [];
    if ($entity->access('restore')) {
      $url_options['attributes']['aria-label'] = $this->t('Restore @label', [
        '@label' => $entity->label() ?? $entity->id(),
      ]);

      $operations['restore'] = [
        'title' => $this->t('Restore'),
        'url' => $entity->toUrl('restore')->mergeOptions($url_options),
        'weight' => 0,
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 880,
          ]),
        ],
      ];
    }
    if ($entity->access('purge')) {
      $url_options['attributes']['aria-label'] = $this->t('Purge @label', [
        '@label' => $entity->label() ?? $entity->id(),
      ]);

      $operations['purge'] = [
        'title' => $this->t('Purge'),
        'url' => $entity->toUrl('purge')->mergeOptions($url_options),
        'weight' => 5,
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 880,
          ]),
        ],
      ];
    }
  }

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity->getEntityTypeId() === 'workspace') {
      foreach (Element::children($build['changes']['list']) as $key) {
        $tracked_entity = $build['changes']['list'][$key]['#entity'];
        if ($this->trashManager->isEntityTypeEnabled($tracked_entity->getEntityType(), $tracked_entity->bundle()) && Trash::entityIsDeleted($tracked_entity)) {
          // Highlight deleted entities in the workspace changes page.
          $build['changes']['list'][$key]['#attributes']['style'] = 'color: #a51b00; background-color: #fcf4f2;';
        }
      }
    }

    if (Trash::entityIsDeleted($entity)) {
      $build['#attributes']['class'][] = 'is-deleted';
      $build['#attached']['library'][] = 'trash/trash';
    }
  }

}
